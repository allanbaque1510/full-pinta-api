<?php

use App\Http\Middleware\ForzarJson;
use App\Http\Middleware\Idempotencia;
use App\Modules\Notifications\Jobs\EnviarNotificacionesProgramadas;
use App\Modules\Notifications\Jobs\ProgramarRecordatoriosCitas;
use App\Modules\Notifications\Jobs\ProgramarSolicitudesResena;
use App\Modules\Reviews\Jobs\RecalcularScoreRanking;
use App\Modules\Scheduling\Jobs\ExpirarHoldsVencidos;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // API pura: no hay rutas web, ni sesión, ni CSRF. La autenticación
        // es por token de Sanctum, que es lo que necesita la app Flutter.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // "No confiar en que el job corrió" (§5.4): el motor de
        // disponibilidad igual filtra `expira_at` en cada consulta, pero los
        // holds vencidos tienen que limpiarse solos, cola `critica`, cada
        // minuto (§6.8). Primer uso del scheduler en el proyecto.
        $schedule->job(new ExpirarHoldsVencidos)->everyMinute()->onQueue('critica');

        // Ranking orgánico (§7.3): "se recalcula por job nocturno, nunca en
        // el request" — cola `batch` (§12.6).
        $schedule->job(new RecalcularScoreRanking)->dailyAt('02:00')->onQueue('batch');

        // Notificaciones (§11): programar (idempotente vía el UNIQUE de
        // `notificacion`, se puede reescanear seguido) y enviar, ambos en la
        // cola `notificaciones` (§12.6). Nunca se envía dentro del request.
        $schedule->job(new ProgramarRecordatoriosCitas)->everyTenMinutes()->onQueue('notificaciones');
        $schedule->job(new ProgramarSolicitudesResena)->everyTenMinutes()->onQueue('notificaciones');
        $schedule->job(new EnviarNotificacionesProgramadas)->everyMinute()->onQueue('notificaciones');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // `prepend`, no `append`: Laravel ordena el stack por prioridad y el
        // middleware de `auth` se adelanta al del grupo. Si ForzarJson corre
        // después, `auth` ya decidió que la petición no espera JSON.
        $middleware->api(prepend: [
            ForzarJson::class,
        ]);

        // No hay ruta `login` que ofrecer a un invitado: esto es una API.
        // Sin esto, una petición sin token revienta con un 500 por
        // RouteNotFoundException en vez de responder 401.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'idempotente' => Idempotencia::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Las peticiones a la API siempre responden JSON. Lo que NO sea /api
        // (una URL mal escrita en el navegador) cae en el manejador normal de
        // Laravel, que en local muestra la pagina de error con el stack trace.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
