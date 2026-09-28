<?php

namespace App\Modules\Identity\Application;

/**
 * Recuperar contraseña admite dos canales — teléfono por WhatsApp o correo
 * (§4.3): el usuario elige cuál usar al tocar "olvidé mi contraseña". Envía el
 * código exista o no una cuenta con ese destino: revelar la diferencia sería
 * confirmar a un atacante si un teléfono o correo está registrado.
 */
final readonly class SolicitarRecuperacionContrasena
{
    public function __construct(private CodigoVerificacion $codigos) {}

    public function __invoke(string $canal, ?string $telefono, ?string $email): void
    {
        match ($canal) {
            'whatsapp' => $this->codigos->enviarATelefono($telefono),
            'email' => $this->codigos->enviarAEmail($email),
        };
    }
}
