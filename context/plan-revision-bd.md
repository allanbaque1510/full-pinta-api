# Revisión de base de datos — plan de cambios

Documento de trabajo de la sesión de revisión del esquema de base de datos junto al usuario.
Formato pregunta → respuesta → (si aplica) cambio acordado.

No reemplaza a [`fullpinta-especificacion.md`](fullpinta-especificacion.md) (sigue siendo la fuente
de verdad) ni a [`plan-implementacion.md`](plan-implementacion.md) (sigue siendo el plan por fases).
Este archivo es el registro de la revisión puntual del esquema: qué se preguntó, qué se decidió y
por qué — para que, si algo aquí implica tocar una migración o la especificación, quede la traza de
la decisión antes de tocar código.

**Estado:** cerrado — 2026-09-29. Todos los cambios acordados en "Cambios pendientes de implementar" quedaron implementados; lo único que sobrevive como pendiente real es el `EnviadorEmail` genérico con plantillas (notificaciones no transaccionales), trackeado aparte en `context/plan-implementacion.md` → "Bloqueadores externos".

---

## Cómo se usa

- Cada entrada es una pregunta real que hizo el usuario sobre el esquema.
- Si la respuesta no implica cambio: se anota igual, breve, para no repetir la pregunta.
- Si implica cambio: se marca **Cambio acordado**, se describe el impacto (qué tabla/migración/spec
  toca) y se deja pendiente de ejecutar hasta que el usuario confirme que se implemente ahora o después.
- Cuando un cambio se implementa, se marca `[x]` y se referencia el commit o la migración resultante.

---

## Preguntas y decisiones

### Bloque: tabla `consentimiento` (LOPDP)

1. **¿Con qué objetivo existe `consentimiento`?** Cumplimiento LOPDP (§13.1): registrar, por finalidad, que el usuario autorizó algo puntual (operar la cita ≠ recibir marketing), de forma probable ante una auditoría. Sin cambio.

2. **¿Qué significan `finalidad`, `origen`, `documento_version`, `otorgado`?** Documentado contra el código real (`OtorgarConsentimiento`, `ActualizarConsentimiento`):
   - `finalidad`: para qué se pide el permiso (4 valores hoy).
   - `otorgado`: cada otorgamiento es una fila nueva (nunca se actualiza); revocar sí actualiza esa misma fila (`revocado_at`).
   - `origen`: hoy siempre `'app'` hardcodeado en el único punto de entrada — el esquema contempla `local`/`web` pero no hay flujo real que los use todavía (relevante para el cliente sombra, ver punto de riesgo de la spec §13.1).
   - `documento_version`: se llena sola desde `config('fullpinta.version_terminos')` — **un único valor global**, no por finalidad. Sin cambio en este punto, pero ver el cambio acordado abajo, que sí lo toca.

3. **¿Tabla de catálogos universal, recursiva (`id_padre`), para todos los enums del sistema?** Descartada. Es el antipatrón "One True Lookup Table" (OTLT): pierde integridad referencial por tipo (una FK ya no puede garantizar a qué catálogo apunta), fuerza columnas nullable por catálogo (deriva a EAV), y no resuelve nada que el patrón actual del proyecto (una tabla de parámetros por catálogo: `vertical`, `tipo_recurso`, `plan`...) no resuelva ya. Tampoco hay ningún catálogo en la spec que necesite jerarquía real de profundidad arbitraria. Si algún catálogo puntual la necesita en el futuro, se le agrega un `padre_id` autoreferenciado **local a esa tabla**, no uno universal. Sin cambio.

4. **¿`finalidad` como `varchar`+`CHECK` viola normalización?** No — cumple 1NF/2NF/3NF (no hay dato redundante que eliminar; no se guarda descripción/metadata duplicada en ninguna fila). El eje correcto para decidir "tabla vs varchar" no es normalización, es: *¿el vocabulario es cerrado y acoplado a lógica de código (se queda varchar+CHECK) o es un catálogo abierto que alguien gestiona como dato (pasa a tabla)?*

5. **¿Alterar un `CHECK` para agregar un valor pone en riesgo los datos existentes?** No — es DDL transaccional en Postgres: o se aplica entero (si todas las filas ya cumplen) o no se aplica nada (si alguna fila lo violaría), nunca queda a medias. Se comparó contra el riesgo real de migrar a FK con catálogo (crear tabla, backfill manual, borrar columna vieja) — esa ruta tiene más pasos donde un humano puede introducir un error real de datos (el backfill no se autovalida como el `CHECK`).

### Bloque: `finalidad` sí necesita convertirse en catálogo (razón real: metadata, no normalización)

6. **¿`finalidad` necesita nombre y descripción propios, no solo el código?** Sí — hoy el texto que ve el usuario en la pantalla de consentimiento está hardcodeado en el frontend Flutter, no en la base. Cambiar ese texto hoy exige desplegar la app móvil. Esto cruza el criterio real de la skill `migracion` ("necesita atributos propios que un varchar no puede cargar"): pasa a tabla de catálogo `finalidad_consentimiento`.

7. **¿`consentimiento` es el documento legal en sí?** No — `consentimiento` es el **recibo/evento** ("quién aceptó qué, cuándo, bajo qué versión"), no el documento. El documento (texto legal con sus versiones) no existe hoy como entidad en el esquema — solo hay un string `documento_version` suelto, sin contenido ni URL asociados. La spec (§13.1) lista esto como una obligación aparte ("política de privacidad y términos versionados"), no cubierta todavía. Se agrega `documento_legal` como tabla propia, desacoplada de `finalidad_consentimiento` (una finalidad no está atada 1:1 a un documento — varias finalidades pueden compartir el mismo documento).

8. **¿Una tabla `documentos` polimórfica y genérica para todo el sistema (legales, comprobantes SRI, futuros contratos...)?** Descartada, mismo razonamiento que el punto 3: `documento_legal` solo tiene un consumidor real (`consentimiento`) — no hay necesidad genuina de que varias tablas no relacionadas apunten a lo mismo (a diferencia de `reporte`, que sí es polimórfica con justificación real: reportes de moderación cuelgan de reseña/foto/local/profesional con el mismo comportamiento). Además, "documento legal", "comprobante SRI" y "contrato" no comparten forma ni ciclo de vida — forzarlos juntos deriva en columnas nullable por tipo (EAV). `documento_legal` se queda específica y dedicada.

9. **¿Se debe mostrar el texto largo (política completa), y dónde vive?** Sí. Se descartó que `documento_legal.url` apunte a una página externa "viva" (rompe trazabilidad si la editan sin subir versión). Se decidió guardar el texto completo en la propia tabla (`contenido text`, markdown), **inmutable una vez publicado** — mismo patrón que ya usa `consentimiento` (nunca se edita una fila, se crea una nueva versión). `text` en Postgres no tiene límite práctico y se guarda comprimido fuera de la fila (TOAST) automáticamente, así que no hay penalidad de rendimiento por guardar texto largo. `url` queda como columna opcional, solo si además quieren una página pública equivalente (no es la fuente de verdad legal).

10. **¿Toda finalidad necesita sí o sí un documento legal?** No — la FK `consentimiento.documento_legal_id` debe ser **nullable**. Hay finalidades con peso legal real (`operacion_servicio`, `transferencia_internacional` — probablemente cubiertas por la Política de Privacidad) y finalidades que pueden bastar con el texto corto de `finalidad_consentimiento.descripcion` sin documento formal detrás (`marketing`, posiblemente). **Cuáles exactamente necesitan documento propio es una decisión legal, no técnica** — queda pendiente de confirmar con un abogado especializado en LOPDP (mismo criterio que ya marca la spec §13 para todo lo de cumplimiento). El esquema solo debe permitir ambos casos, no forzar uno.

### Bloque: tabla `usuario`

11. **¿Falta información en `usuario`?** Se revisó contra el código real (no solo la migración) y se encontraron 3 vacíos genuinos: (a) sin verificación de propiedad del email, (b) sin ningún endpoint de recuperar/cambiar contraseña (verificado: el `AuthController` solo tiene 7 métodos y ninguno es esto), (c) el enum `notificacion.canal` no incluye `'email'`, necesario para el evento que la spec (§11.2) documenta explícito ("Suscripción por vencer → Push + email") y que quedó diferido en la Fase 9 sin resolverse ni siquiera después de completarse la Fase 10 (Billing) de la que dependía. Se descartaron como vacíos: bloqueo/baneo de cuenta (la spec §5.6 limita el techo de moderación de clientes a `requiere_confirmacion`, deliberadamente nunca bloquea) y zona horaria/idioma en `usuario` (mercado de un solo huso horario y un solo idioma, ya justificado en Fase 5).

12. **¿Para qué sirve verificar el email, si ya es `UNIQUE`?** `UNIQUE` solo evita que dos cuentas usen el mismo string de correo — no prueba que quien lo escribió sea el dueño real de esa bandeja. Sin verificación, alguien puede registrar el correo de un tercero y "adueñárselo" en la plataforma, bloqueando a su dueño real de usarlo después. Es un motivo real y distinto al de evitar duplicados.

13. **¿Faltaban `fecha_nacimiento`/`genero`?** No faltaban — ya existían, pero mal ubicados: estaban en `cliente_perfil`, mezclados con métricas que sí son específicas del comportamiento como cliente (`no_shows`, `cancelaciones_tardias`, `requiere_confirmacion`). Género y fecha de nacimiento son atributos de la persona, no de su relación con el marketplace — le sirven igual a un dueño o a un profesional que a un cliente. Se acuerda moverlos a `usuario`. Impacto en código verificado como mínimo: solo aparecen como un `cast` en el modelo `ClientePerfil`, ningún controller/endpoint los usa todavía.

14. **¿El usuario debe quedar atado a un solo rol?** No — es decisión central y deliberada del proyecto, no un vacío. `ResolverContexto`/`GET /auth/contexto` lo documentan explícito: un mismo `usuario` puede ser cliente, dueño de un negocio y profesional en otro local, todo a la vez (ejemplo real ya contemplado en el código: "el dueño que también corta pelo ahí"). `cliente_perfil` no es una fila de rol — "ser cliente" es una capacidad de fondo de cualquier cuenta (`es_cliente` nunca se calcula de una tabla, ver punto 15). Los roles reales del sistema ya existen, correctamente separados por dominio: `negocio_miembro` (lado negocio: propietario/admin/recepción) y `asignacion` (lado staffing: barbero/estilista/manicurista/groomer) — fusionarlos en una tabla de roles genérica caería en el mismo antipatrón ya descartado para catálogos y documentos (atributos y entidades referenciadas distintas).

15. **¿`es_cliente: true` en `GET /auth/contexto` es innecesario?** Sí — está hardcodeado, nunca varía (no existe ningún caso en la spec donde una cuenta no pueda ser cliente), así que no aporta información ni necesita ningún branch en el frontend. Se acuerda quitarlo del payload y dejar la regla en prosa en `docs/api-referencia.md`.

### Bloque: tabla `mascota` e imágenes (polimórfico)

16. **¿`especie` debería ser catálogo?** Sí — a diferencia de `finalidad`/`canal`, no gatilla ninguna lógica de negocio (verificado: cero referencias en todo el código), es puramente descriptivo, y ya hay precedente exacto en el propio esquema (`tamano_mascota`, tabla de parámetros para el mismo tipo de dato). Se crea `especie_mascota`, sembrada solo con `perro`/`gato`/`otro` por ahora — sin invertir en especies exóticas todavía porque grooming está apagado en la v1 (§16.6).

17. **¿`raza` debería ser catálogo?** Se recomendó inicialmente texto libre (ya lo es hoy) por el costo de mantener una lista exhaustiva y porque los mestizos/criollos (muy comunes en Ecuador) no encajan bien en un catálogo cerrado. El usuario prefirió catálogo, resuelto incluyendo `"Mestizo"` como una fila más — mismo patrón que `"otro"` ya usa en `especie`. Se crea `raza_mascota`, acotada por `especie_id` (una raza pertenece a una sola especie: Labrador no debería aparecer al registrar un gato).

18. **¿Tabla polimórfica para imágenes (`usuario`, `local`, `profesional`, `mascota`)?** A diferencia de la tabla de catálogos/documentos universal (descartada antes), aquí sí se aceptó: las fotos comparten forma casi idéntica entre entidades (url, orden, dueño), es un patrón legítimo y común en sistemas reales (Eloquent tiene `MorphMany`/`MorphTo` nativo para esto). Reemplaza a `local_foto` y `profesional_foto` (con su migración de datos). Se acuerda usar `Relation::morphMap()` con códigos cortos (`'local'`, `'profesional'`, etc.) en vez del nombre de clase completo — si no, Laravel guarda el FQCN de la clase PHP y renombrar/mover un modelo deja filas huérfanas silenciosamente.

19. **¿El "tipo" de imagen (perfil/portada/fachada/interior/muestra) va como `varchar`+`CHECK` o catálogo?** Catálogo (`tipo_imagen`) — a diferencia de `local_foto.tipo` original (acotado a una sola entidad), ahora sirve a 4 entidades con subconjuntos distintos de valores, y sí hay valor real en `nombre`/`icono`/`orden` propios para una UI de gestión de fotos.

20. **¿Cómo saber cuál es la foto de perfil/portada vigente?** Puntero directo (`foto_perfil_id`/`portada_imagen_id` → FK a `imagen.id`) en vez de un índice único parcial sobre `tipo`. Ventaja real: permite conservar el historial de fotos anteriores sin conflicto (no hace falta que exista una sola fila por tipo), y simplifica el diseño — ya no hace falta desnormalizar ningún código para habilitar un índice. **Resuelto por entidad, confirmado con el usuario:**
    - `local` → **no** lleva puntero — se queda como galería pura (`fachada`/`interior`/`muestra` vía `tipo_imagen`), sin foto de perfil ni portada singular.
    - `negocio` → **sí** lleva ambos: `foto_perfil_id` (logo de marca) y `portada_imagen_id`.
    - `profesional` → **sí** lleva `foto_perfil_id`, propia e independiente — ver pregunta 20b, no se deriva de `usuario`.
    - `usuario`/`mascota` → `foto_perfil_id`, como ya se había definido.
    - Reemplaza a `usuario.foto_url` y `profesional.foto_url` (se derivan de la relación en vez de duplicarse).

20b. **¿La foto de perfil de `profesional` debería ser la misma que la de `usuario` vinculado?** No — deben ser independientes, por dos razones concretas: (1) `profesional.usuario_id` es **nullable**, y la propia especificación dice explícito que *"muchos barberos no van a instalar nada"* — si la foto dependiera de tener `usuario`, la mayoría de los profesionales se quedarían sin ninguna foto, contradiciendo el motivo por el que existe el portafolio (*"la gente escoge barbero viendo cortes, no leyendo precios"*). (2) Aunque sí exista el vínculo, son audiencias distintas: la foto de `usuario` es el avatar personal (cómo se ve como cliente de la plataforma), la de `profesional` es la foto pública de cara al cliente que busca barbero — pueden razonablemente ser distintas. Esto ya era así en el esquema actual (`usuario.foto_url` y `profesional.foto_url` son columnas independientes hoy) — el rediseño solo preserva esa separación, no la cambia.

22. **¿`favorito` debería ser polimórfica, como `imagen`?** No — se queda como está (dos FK nullable + `CHECK` XOR). A diferencia de `imagen` (4 tipos, con código duplicado real que consolidar) y de `reporte` (moderación, un concern transversal que crece con cualquier contenido nuevo), `favorito` tiene solo 2 tipos fijos por la naturaleza del producto (un lugar + una persona) — revisada la spec completa, no hay ninguna señal de un tercer tipo, y el código ya es un solo servicio simple (`FavoritoService`) sin duplicación que resolver. Mantener las FK reales es estrictamente mejor aquí: integridad referencial gratis, sin ningún costo real que estemos evitando.

23. **¿La tabla `plan` necesita más información?** Sí, dos vacíos reales:
   - Los límites/capacidades de cada plan (§9.4: locales, profesionales, fotos, desglose de liquidación, recordatorios WhatsApp, responder reseñas, estadísticas, promociones, destacados) no viven en ningún lado hoy — se comprueban con `Negocio::esPro()` comparando el string `$this->plan->codigo === 'pro'` a mano, en cada lugar que lo necesita.
   - Los coeficientes de precio (`$8` base, `$5` adicional, 10 meses de pago anual) están hardcodeados como constantes PHP en `SuscripcionService`, no en `plan` — más urgente que los límites, porque cambiar precios sí ocurre con frecuencia real. Además la fórmula actual no distingue el plan al que se aplica (bug latente: si se llamara `activar()` con `plan: 'free'`, igual cobraría según la fórmula de Pro).

24. **¿`smallint` o `int` para los límites nuevos de `plan`?** `smallint` — coincide con `suscripcion.profesionales` (misma magnitud real: cuántos profesionales tiene un negocio), y el rango (hasta 32.767) sobra por mucho para cualquier escenario real de este mercado. `int` no aporta nada a cambio de los 2 bytes extra.

25. **¿El precio individual por negocio (con posibles descuentos) sigue funcionando si `plan` gana `precio_base`/`precio_adicional`?** Sí — esas columnas son solo la fórmula de referencia/default para sugerir el precio al activar una suscripción nueva. `suscripcion.precio_mensual` sigue siendo el valor real, individual, ya guardado como columna propia (no derivado al leer) — eso es lo que permite que un descuento negociado se aparte del default sin tocar el esquema. Ya estaba bien diseñado así desde antes.

### Bloque: `suscripcion` y ciclo de facturación

26. **¿Cómo se distingue pago mensual de anual, y cómo se sabe cuándo generar el siguiente cobro?** `suscripcion.ciclo` (`mensual`/`anual`) y `vigente_hasta` (fecha) ya existen y se calculan bien al activar. Pero se confirmó, revisando el código completo, que **nada usa esos datos todavía**: no hay ningún job programado que revise vencimientos (`bootstrap/app.php` no tiene ningún `Schedule::` para Billing); `CobroService::registrar()` (el método que generaría el cobro del siguiente período) existe pero **nadie lo llama en todo el proyecto**; no hay ni un endpoint para crear un cobro nuevo (solo `GET` listar y `POST .../marcar-pagado`); y los estados `gracia`/`vencida` de `suscripcion.estado` están definidos en el `CHECK` del esquema pero son inalcanzables en la práctica — ningún código transiciona a ellos, solo `cancelada` se usa de verdad. Esto también es lo que bloquea la notificación documentada en §11.2 ("Suscripción por vencer, 7 días antes"), que además depende del canal `email` (ver pregunta 11 del bloque `usuario`).

27. **¿Al cancelar una suscripción pagada por adelantado (ej. anual), se respeta el período ya pagado?** Hoy **no** — `SuscripcionService::cancelar()` baja el negocio a Free **de inmediato**, sin importar cuánto período pagado quede por delante. Es una decisión ya documentada en Fase 10, pero que mezcló dos conceptos distintos: *"no hay período de gracia para reintentar un cobro fallido"* (correcto — sin pasarela de pago no hay nada que reintentar) con *"el cliente que cancela por su cuenta pierde lo que ya pagó"* (esto no debería pasar, y es un problema real de equidad con el cliente, no solo técnico). Confirmado con el usuario: el comportamiento esperado es que, al cancelar, el negocio siga disfrutando el plan hasta que se cumpla el período ya pagado (`vigente_hasta`), y recién ahí baje a Free sin generarle un cobro nuevo.

### Bloque: tabla `negocio`

29. **¿Falta agregar imágenes (perfil/portada) a `negocio`?** Sí — no tiene ningún campo de foto hoy. Se suma como quinto tipo en el sistema `imagen`/`tipo_imagen` ya diseñado (vía `Relation::morphMap()`), con `foto_perfil_id` para el logo de marca — tiene sentido real porque un negocio con varias sucursales (ej. 3 locales) probablemente quiere una identidad de marca consistente entre todas, separada de las fotos propias de cada local (fachada/interior). Se suma a la pregunta abierta ya registrada sobre `portada_imagen_id` (antes solo `local`/`profesional`, ahora también `negocio`).

30. **¿`negocio` necesita más información — sobre verificación?** Sí, se encontró una inconsistencia real de nivel: la spec dice que el badge "Verificado" se gana con *"validación de local y RUC"*, pero el RUC pertenece al **negocio** (§4.4: *"el negocio es la marca (dueño, RUC, suscripción)"*), mientras que `verificado`/`verificado_at` viven solo en `local` — un negocio con varias sucursales tendría que revalidar el mismo RUC una vez por cada local, sin ningún lugar donde quede registrado que ya se confirmó. Se acuerda separar: `negocio.ruc_verificado`/`ruc_verificado_at` (una sola vez por negocio) + `local.verificado`/`verificado_at` (se queda igual, por sucursal física) — el badge público que ve el cliente combina ambos. **Nota adicional:** revisando el código, hoy no existe ningún flujo ni endpoint que verifique nada en ningún nivel — `LocalService::crear()` solo pone `verificado: false` por defecto y ahí queda para siempre. Mismo vacío ya documentado de "falta panel de soporte de plataforma" que `solicitud_catalogo`/`reporte`.

### Bloque: `horario_local`

32. **¿`horario_local` soporta un local que cierra después de medianoche (ej. abre 18:00, cierra 02:00)?** Se investigó a fondo porque el `CHECK cierra > abre` rechaza esa fila directo, igual que pasa con `turno`. **Verificado con evidencia real, no solo lectura de código:** el test obligatorio `test_un_turno_partido_en_medianoche_ofrece_slots_en_ambos_dias` (`DisponibilidadTest.php:256`) ya arma el `horario_local` partido en dos filas (miércoles 18:00–23:59 + jueves 00:00–02:00) como parte de su fixture, y sus aserciones confirman que aparecen slots correctos en ambos días. Mismo patrón que ya usa `turno` (partir en dos filas), y ya funciona sin ningún cambio de código — cada fila se ancla a su propio `dia_semana`/fecha, sin el problema de inversión que se sospechó en un primer análisis superficial. **Sin cambio.** Nota cosmética, no urgente: el nombre del test solo menciona "turno partido", sin dejar explícito que también cubre `horario_local` — se podría aclarar en el nombre/comentario para que no haya que redescubrirlo leyendo el fixture.

### Bloque: `amenidad` / `amenidad_categoria`

34. **¿La categoría `pago` de `amenidad` está bien modelada?** No — encontrado un duplicado real de vocabulario: los 4 códigos de la categoría `pago` (`efectivo`, `transferencia`, `tarjeta`, `payphone`) son exactamente los mismos 4 valores de `cita.metodo_pago` (`varchar`+`CHECK`), mantenidos en dos mecanismos completamente distintos y **sin ninguna conexión entre ambos** — verificado en código: `cita.metodo_pago` nunca se valida contra lo que el local declaró aceptar en `local_amenidad`. Es el mismo patrón que la skill `migracion` ya identificó y corrigió en Fase 3 (mismo `varchar`+`CHECK` repetido en más de una tabla). Confirmado con el usuario: se extrae a un catálogo compartido `metodo_pago`, porque además un local puede aceptar uno o varios métodos y eso se espera que crezca con el tiempo (billeteras digitales, etc.).

35. **¿El nombre de `amenidad`/`amenidad_categoria`/`local_amenidad` comunica bien su propósito?** Sí — `amenidad_categoria` (el grupo) → `amenidad` (el ítem, comparte la raíz del nombre del padre) → `local_amenidad` (pivote, convención estándar de Laravel: nombre de las dos tablas que conecta). Mismo patrón que `notificacion_categoria` → `notificacion`. **Observación menor de consistencia** (no requiere cambio): `servicio_categoria` → `catalogo_servicio` rompe ese mismo patrón (el hijo no comparte la raíz "servicio" sola) — inconsistencia de nomenclatura entre tres pares de tablas que deberían leerse igual. No amerita renombrar (tocar nombres de tabla en producción no es gratis), solo queda anotado.

### Bloque: `negocio_miembro` y `vertical`

37. **¿`negocio_miembro` debería relacionarse con `profesional` en vez de con `usuario`?** No — `usuario_id` es correcto. `negocio_miembro` (`propietario`/`admin`/`recepcion`) responde *"¿quién puede administrar este negocio?"*, una pregunta de identidad/cuenta, no de capacidad de prestar un servicio. Si apuntara a `profesional`, cada propietario/admin necesitaría una fila artificial en `profesional` (aunque no preste ningún servicio), y como `profesional.usuario_id` es nullable, podría darse el absurdo de un "propietario" sin cuenta con la que iniciar sesión. Mismo principio que ya se confirmó con `ResolverContexto`: ser dueño y ser profesional son capacidades independientes de la misma persona (`usuario_id` compartido), nunca una a través de la otra. Sin cambio.

38. **¿`vertical` es un buen nombre, o debería llamarse distinto (ej. `servicio`)?** Se analizó a fondo: "vertical" no está mal usado — es terminología real de negocio (segmento de industria que atiende la plataforma) y además es la palabra que la propia especificación ya usa de forma consistente y deliberada en 6+ lugares distintos. `servicio` sería **peor**, no mejor: colisionaría con `catalogo_servicio`/`servicio_local`/`servicio_categoria`, que ya significan algo distinto y más concreto en este mismo esquema. **Decisión del usuario: renombrar a `rubro`** de todos modos (más natural en español ecuatoriano — "¿en qué rubro está tu negocio?" — sin necesidad de jerga de startup), aceptando el costo del cambio.

### Bloque: `servicio_local`

40. **¿`CitaService` aplica el precio/duración por tamaño de mascota (`servicio_local_tamano`) al agendar?** No — vacío real confirmado en el código, no solo en el esquema. `CitaService::crear()` **sí recibe** `mascota_id` (lo guarda en la cita), pero `cargarServicios()` solo lee `ServicioLocal::whereIn('id', $ids)...` y arma `cita_item` con `$servicio->precio`/`$servicio->duracion_min` **siempre planos** — nunca consulta `servicio_local_tamano`. Confirmado también que `servicio_local_tamano` no aparece ni una sola vez en todo el módulo Scheduling (sí tiene CRUD completo en Catalog, pero el motor de agendamiento la ignora). Consecuencia si se activara grooming tal cual: un baño de golden retriever cobraría lo mismo que uno de chihuahua — exactamente lo que la migración original advertía evitar. **No urgente hoy** porque la vertical `mascotas` está deliberadamente apagada en la v1 (§16.6) — pero debe resolverse **antes** de activarla, no después.

41. **¿Falta algo más en `servicio_local`?** Se revisó "promociones y horas valle" (§9.4, función exclusiva Pro) — no tiene **ningún** modelo de datos en ningún lado de la especificación: no hay tabla, no aparece como fila en la matriz de eventos de notificación (§11.2), no se define qué cuenta como "hora valle" ni cómo se calcula el descuento. Es un nombre en la tabla comparativa de planes, nada más. No se diseña un mecanismo ahora — inventar la estructura sin que el negocio defina la regla real sería especular sobre un requisito que todavía no existe. Queda anotado como pendiente de **definición de producto**, no de esquema.

### Bloque: `solicitud_catalogo`

42. **¿Está completa `solicitud_catalogo`?** Es deliberadamente mínima — revisado el servicio completo (`SolicitudCatalogoService.php`, 15 líneas): solo `crear()`/`listar()`, sin `aprobar`/`rechazar`, documentado explícito en el propio código como pendiente del panel de soporte de plataforma que todavía no existe (mismo patrón que `reporte` y la verificación de `local`/`negocio`). No es un vacío nuevo, es el mismo ya conocido en tres lugares del esquema.

43. **¿Le falta registrar quién (qué `usuario`) hizo la solicitud?** Sí — hoy solo guarda `local_id`, sin ningún registro de cuál de los posibles miembros del local (`propietario`/`admin`/`recepcion`) la envió. Se acuerda agregar `solicitante_id` (FK a `usuario`), mismo criterio de nombre que ya usa `reporte.reportante_id` para el mismo tipo de dato (quién originó el registro). **Se deja fuera, a propósito:** protección por `Idempotency-Key` — el riesgo de duplicado existe (mismo origen que motivó la regla en `cita`), pero la consecuencia es leve (una solicitud repetida visible dos veces, no una reserva ni un cobro duplicado) y el §12.4 ya acota esa protección solo a los verbos que la spec nombra explícito; agregarla aquí sería inventar contrato donde la especificación no lo pide, mismo criterio ya aplicado en Fase 6 para otros endpoints.

### Bloque: `producto`

45. **¿`producto` debería tener catálogo maestro, como los servicios?** No — es un vocabulario abierto de marcas comerciales (pomada de tal marca, cera de tal otra), no un conjunto cerrado de tipos de industria como los servicios. Confirmado además que no hay ningún filtro de búsqueda pública por producto en la spec (los filtros de v1 son: vertical/rubro, servicio, precio, distancia, disponibilidad, amenidades, abierto ahora — producto no está) — el motivo que justifica `catalogo_servicio` (proteger la búsqueda) no aplica aquí.

46. **¿Los clientes pueden ver los productos de un local hoy?** No — verificado en código: `ProductoController::index()` exige `authorize('ver', $local)`, y `LocalPolicy::ver()` requiere tener algún rol en ese local (`propietario`/`admin`/`recepcion`). Es un endpoint de gestión para el staff, no de exhibición pública. Confirmado con el usuario: sí deben verse al consultar el perfil del local/negocio — se resuelve sumándolos a `LocalService::perfilPublico()` (mismo mecanismo cacheado que ya expone servicios/amenidades/fotos), no con un endpoint nuevo aparte. El endpoint de gestión existente no cambia.

47. **¿`producto` necesita descripción e imagen?** Sí, confirmado con el usuario. Se agrega `descripcion text` y `foto_id` (vía el sistema `imagen`/`tipo_imagen` ya construido, reusando el tipo `muestra` en vez de crear uno nuevo solo para esto). Sigue sin existir ningún flujo de compra — es exhibición informativa, igual que las fotos de trabajo del local; no toca la exclusión de "cualquier flujo de dinero por la plataforma" de la v1.

### Bloque: `profesional`

49. **¿`profesional.independiente` está bien, o duplica algo?** Duplica `asignacion.modalidad` (`empleado`/`renta_silla`/`invitado`), a un nivel de granularidad incorrecto — mismo patrón que ya vimos con `metodo_pago`. `independiente` es un solo booleano **global a la persona**, pero un profesional puede tener varias `asignacion` en locales distintos, cada una con su propia `modalidad` — puede ser empleado en un local y rentar silla en otro a la vez, algo que `profesional.independiente` no puede representar sin contradecirse. Confirmado en código que ninguna de las dos columnas alimenta lógica de negocio todavía (ni comisión, ni liquidación) — pero cuando se use, la fuente correcta debe ser `asignacion.modalidad`, la única que puede variar por local. Resto de la tabla revisado y correcto: `perfil_publico` sí se aplica de verdad (404 real confirmado en `ProfesionalService`), `traslado_min`/`bio`/`alias` bien pensados. **Se acuerda eliminar `profesional.independiente`.**

### Bloque: `asignacion`

51. **¿Se puede tener dos asignaciones vigentes a la vez para el mismo (profesional, local)?** Sí, hoy no hay nada que lo impida — vacío real confirmado en código: `AsignacionService::crear()` inserta sin verificar si ya existe una fila vigente (`hasta IS NULL`) para ese mismo par. Esto es un riesgo de corrección real, no solo teórico: `CitaService::comisionPct()` resuelve la comisión con `->vigente()->value('comision_pct')`, y si hubiera dos filas vigentes tomaría **una cualquiera**, sin orden garantizado — la comisión congelada en `cita_item` podría ser la equivocada, en silencio. El propio docblock de la tabla confirma la intención (*"varias asignaciones vigentes a la vez, **en locales distintos**"* — nunca en el mismo local repetido). Se acuerda agregar protección.

    **Nota menor descartada como problema:** `asignacion.rol` incluye `'recepcion'`, igual que `negocio_miembro.rol`. Parece duplicación pero no lo es — responden preguntas distintas (`negocio_miembro` = acceso al sistema; `asignacion` = turno físico de cobertura de mostrador, sin necesitar `habilidad`). Solo se encontró que el docblock de `ResolverContexto` no menciona `'recepcion'` entre los roles posibles de `asignacion` — desajuste de documentación, sin impacto funcional, sin cambio de código.

### Bloque: `turno` / `turno_fecha` — ⚠️ hallazgo de prioridad alta

52. **¿El motor de disponibilidad verifica que la `asignacion` siga vigente, no solo el `turno`?** No — **bug real confirmado en código**, no cosmético. La spec (§5.1, filtro #2) exige explícitamente dos condiciones separadas: *"el profesional tiene asignación vigente en ese local Y turno vigente ese día"*. `DisponibilidadService::ventanasTurno()` solo verifica lo segundo (`Turno::...->vigenteEn($fecha)`) — no hay ninguna consulta a `Asignacion` en todo el archivo. Y `AsignacionService::terminar()` (cuando se termina la relación laboral de un profesional con un local) **solo** actualiza la fila de `asignacion`, sin cerrar los `turno` asociados. **Consecuencia real:** si un local termina a un profesional y nadie cierra manualmente sus `turno`, el motor seguiría ofreciendo slots con esa persona indefinidamente — un cliente podría agendar con alguien que ya no trabaja ahí. Es el componente que la propia spec marca como *"el único donde un bug cuesta clientes y reputación"* (§5, intro). **Prioridad alta.**

53. **¿`turno` y `turno_fecha` son redundantes?** No — `turno` es la regla recurrente semanal (se repite cada semana dentro de su rango de vigencia); `turno_fecha` es una excepción puntual a **una fecha exacta**, con tres variantes (`extra`/`reemplaza`/`cancela`), aplicada **después** de calcular el recurrente, sin alterar la regla general. Confirmado que `turno` en sí está bien construido (constraint `EXCLUDE` correcto, cruce de medianoche ya verificado con test real en la pregunta 32). Sin cambio en esta parte.

### Bloque: `recurso` — necesidad real y fricción de onboarding

55. **¿Es realmente necesario el mecanismo de `recurso`?** Depende de la vertical, y se discutió a fondo con el usuario. Técnicamente sí es necesario para verticales con capacidad física **compartida y limitada** (uñas: `mesa_unas`; mascotas: `tina`), donde el número de profesionales puede superar el número de estaciones físicas — ahí `recurso` evita sobreventa real (3 manicuristas, 2 mesas). Pero para barbería no aporta nada en la práctica: normalmente el número de sillas no es menor al de barberos trabajando, así que el profesional (`asignacion`/`turno`) ya es el límite real, y exigir gestionar `recurso` ahí es solo fricción sin beneficio. Verificado también: `recurso` en sí (el `RecursoService`, las validaciones, `recursoLibre()`) está bien construido — el problema nunca fue la calidad del código, fue exigirlo donde no hace falta.

56. **¿Cómo se resuelve sin perder la protección donde sí importa?** Se corrige el **seed del catálogo maestro**, no el esquema ni el motor: los servicios de barbería pasan de `tipo_recurso = 'silla'` a `'ninguno'`. Como `DisponibilidadService` solo invoca `recursoLibre()` cuando el servicio pedido tiene un `tipo_recurso_id` distinto de `'ninguno'`, la disponibilidad de barbería queda gobernada 100% por `asignacion`/`turno` — sin necesitar crear ni gestionar ningún `recurso`. Uñas y mascotas se quedan exactamente igual, con su `tipo_recurso` real. Cambio de bajo riesgo: una línea de seed, sin tocar el constraint `EXCLUDE` ni el motor.

57. **¿El registro de un local contempla crear los recursos que va a necesitar?** No — confirmado en código, `LocalService::crear()` no tiene ninguna conexión con `recurso`. El riesgo (activar un servicio de uñas/mascotas sin ningún `recurso` activo del tipo correspondiente → cero slots para siempre, sin ningún aviso) sigue existiendo para esas verticales tras el cambio anterior. El punto correcto para resolverlo no es el registro del local (todavía no sabe qué servicios ofrecerá) sino `ServicioLocalService::crear()`: al activar un servicio que necesita un `tipo_recurso`, si el local no tiene ningún recurso activo de ese tipo, se crea uno por defecto automáticamente (ej. "Mesa de uñas 1") — sin bloquear con un error ni agregar un paso extra al dueño.

### Bloque: `excepcion` y validación real al agendar — ⚠️ hallazgo de prioridad alta

58. **¿Qué es `excepcion`?** Bloqueo temporal de disponibilidad, con tres combinaciones válidas según qué FK se llene (el `CHECK` exige al menos una, no exactamente una, a propósito): solo `local_id` → bloquea todo el local; solo `profesional_id` (sin `local_id`) → bloquea al profesional en **todos** sus locales; `profesional_id` **y** `local_id` juntos → bloquea al profesional solo en ese local puntual; solo `recurso_id` → bloquea esa unidad física. 5 motivos posibles (`feriado`/`vacaciones`/`mantenimiento`/`personal`/`bloqueo_manual`), solo clasificación, no cambia el comportamiento del bloqueo.

59. **¿Se valida `excepcion` (y `habilidad`) al momento de agendar, o solo al mostrar disponibilidad?** **No se valida al agendar — hallazgo grave confirmado en código.** `Excepcion` solo aparece en `DisponibilidadService` (decide qué slots *mostrar*) y en el listener de invalidación de caché — **cero apariciones en `CitaService.php`**. Mismo problema, confirmado también, con `habilidad` (¿tiene el profesional la destreza para el servicio pedido?): tampoco se revisa en `CitaService::crear()`. Ninguna de las dos protecciones puede ser un constraint `EXCLUDE` de Postgres, porque ese mecanismo solo compara filas de la **misma** tabla — `cita` contra `excepcion`/`habilidad` (tablas distintas) requiere sí o sí una verificación de aplicación. **Consecuencia real:** un request que no pase por la pantalla de disponibilidad (o una condición de carrera: staff crea una excepción justo cuando un cliente con la pantalla ya cargada confirma) podría agendar en un horario bloqueado, o con un profesional sin la habilidad requerida, sin que nada lo impida. Mismo nivel de prioridad que el hallazgo de `asignacion`/`turno` (pregunta 52) — es el mismo tipo de bug (el motor de disponibilidad filtra correctamente para *mostrar*, pero nadie vuelve a verificar al *guardar*), en tres lugares distintos del mismo módulo.

### Bloque: `cliente_local` (continuación)

61. **¿Con qué objetivo el local "califica" al cliente en `cliente_local`?** No es una calificación — se aclaró que es un concepto distinto de la confiabilidad del cliente, que ya vive aparte en `cliente_perfil` (`no_shows`/`cancelaciones_tardias`/`requiere_confirmacion`, nunca mostrado como puntaje — *"es hostil y lo espanta"*). `cliente_local.nota` es **continuidad de servicio**: preferencias concretas del cliente (corte, fórmula de tinte) para que el mismo profesional no tenga que volver a preguntar en cada visita, y para que un profesional suplente pueda replicar lo que el cliente espera si el titular no está. Confirmado con el usuario: se mantiene la función con esta aclaración.

62. **¿Se puede pulir el diseño?** Sí — ver el cambio pendiente: se quitan los tres contadores (calculados al vuelo contra `cita` en vez de mantenerse como estado guardado, menos riesgo de desincronización), se desacopla `cliente_nuevo` de `cliente_local`, y se construyen los dos endpoints que hoy no existen (ver la pregunta anterior sobre esta tabla — confirmado que no hay ningún Controller/Resource/Request para ella).

64. **¿Conviene un trigger de Postgres para mantener `cliente_local` sincronizado con `cita`?** No — este proyecto ya resuelve *"cuando pasa X en una tabla, algo debe reaccionar en otro lado"* con eventos de dominio + Listeners de PHP (`TurnoModificado`/`ExcepcionModificada` → `InvalidarCacheDisponibilidad`), nunca con triggers de SQL. Un trigger metería lógica de negocio fuera de `Application/`, invisible para quien lee `CompletarCita.php` sin saber que debe ir a revisar la base de datos aparte. Los triggers se reservan para lo que **solo** Postgres puede garantizar bajo concurrencia (`EXCLUDE`, columnas generadas) — mantener un contador no es ese caso, la aplicación ya puede hacerlo de forma confiable. Con la decisión de quitar los contadores (pregunta 62) esto queda sin efecto práctico aquí, pero el criterio se documenta para no repetir la pregunta con otra tabla.

65. **¿Calcular al vuelo es lo bastante rápido para los dos usos reales — ficha individual y bandeja mensual de clientes con recurrencia?** Sí, para ambos, pero con soluciones distintas:
    - **Ficha individual** (recepción consulta un solo cliente puntualmente): `COUNT`/`MIN`/`MAX` sobre `cita` filtrado por `cliente_id`+`local_id`+`estado` — consulta poco frecuente, milisegundos con el índice correcto. Se agrega `(cliente_id, local_id, estado)`.
    - **Bandeja mensual** (todos los clientes del mes con su recurrencia): **no** es un problema de N+1 que un contador por cliente resolvería — es una sola consulta `GROUP BY cliente_id` sobre `cita` (filtrada por `local_id`+`estado`+rango de fecha), que trae la recurrencia de todos los clientes en una sola pasada, sin importar si son 5 o 500. Se cachea con TTL simple (mismo patrón que `BusquedaLocalService`, no con invalidación por evento como `disponibilidad_dia`, porque es puro reporte y no gatilla ninguna decisión de agendamiento).

### Bloque: `cita` / `cita_item` — ⚠️ ampliación del hallazgo de prioridad alta

67. **¿Se valida que `profesional_id`, `servicio_local_id` y `recurso_id` del request pertenezcan al `local` de la ruta?** No — mismo patrón de bug que el ya registrado (preguntas 52 y 59), tercera y cuarta instancia. Confirmado en `CrearCitaRequest`: `Rule::exists(...)` solo comprueba que el registro existe **en algún lugar del sistema**, nunca que pertenece al `$local` de `POST /locales/{local}/citas`. Confirmado también en `CitaService::cargarServicios()`: filtra por `id`+`activo`, nunca por `local_id`. **Consecuencia real:** se podría agendar en un local con un profesional que solo trabaja en otro, con un `servicio_local` (precio/duración/comisión) de otro local, o con un `recurso_id` que es la silla de otro local. La corrección de `asignacion` vigente ya planeada (pregunta 52) resuelve gratis el caso de `profesional_id` (verificar asignación vigente en ese local ya excluye a quien no trabaja ahí) — faltaba agregar la verificación de `servicio_local`/`recurso` contra `local_id`, que no queda cubierta por ningún cambio anterior. Se agrega a la misma corrección de prioridad alta.

### Bloque: `cita_producto` y `cita_evento`

69. **¿`cita_producto` tiene el mismo bug que `cita_item`/`recurso`?** Sí — **quinta instancia** confirmada. `CitaController::agregarProducto()` resuelve `Producto::findOrFail($request->validated('producto_id'))` y `AgregarProductoRequest` solo valida `Rule::exists('producto', 'id')->where('activo', true)` — nunca que el producto pertenezca al `local_id` de la cita. Se agrega a la misma corrección de prioridad alta.

70. **¿`cita_evento` está bien diseñada?** Sí, en general — es el historial inmutable de transiciones de estado de una cita, se llena automático vía el trait `RegistraEventoCita` en cada clase de `Application/Transiciones/`. **Nota menor:** `actor_usuario_id` es un `uuid` suelto, sin FK real hacia `usuario` — a diferencia de casi todo lo demás en este esquema, muy consistente en usar `foreignUuid()`. Parece un descuido, no algo grave (no hay riesgo de apuntar a la tabla equivocada). Bajo impacto — se podría agregar la FK (`nullOnDelete()`, ya que `usuario` nunca se borra de verdad, solo se anonimiza) por prolijidad.

71. **¿Debería `cita_evento` renombrarse a "historial"?** No literalmente "historial" — colisiona con *"Historial de citas del cliente"* (`GET /mis-citas`, Fase 7), que ya significa algo distinto: la **lista de citas pasadas** de un cliente (varias filas de `cita`), no los cambios de estado de **una sola** cita. Se acuerda renombrar a **`cita_bitacora`** — término ya establecido en español para un registro cronológico e inmutable, sin colisión con nada existente en el proyecto.

### Bloque: `disponibilidad_dia` — ⚠️ hallazgo de prioridad alta

73. **¿`disponibilidad_dia` se recalcula cuando se crea o cancela una cita?** No — vacío real confirmado en código. `InvalidarCacheDisponibilidad` (el único listener que dispara `ReconstruirDisponibilidadDia`) solo escucha `TurnoModificado` y `ExcepcionModificada` — **ningún evento de `Cita`** (`CitaCreada`, `CitaCancelada`) está conectado, confirmado también revisando `SchedulingServiceProvider` completo. **Consecuencia real:** cuando un cliente ocupa el último cupo disponible de un local, `slots_libres` no baja — el filtro de búsqueda *"disponible hoy/mañana"* seguiría recomendando ese local a otros clientes con información incorrecta, hasta que por coincidencia un cambio de turno/excepción dispare la reconstrucción de ese día. Mismo nivel de prioridad que los demás hallazgos del motor de disponibilidad — no genera una doble reserva (el `EXCLUDE` sigue protegiendo eso), pero sí un dato de búsqueda sistemáticamente desactualizado.

### Bloque: `idempotencia`

75. **¿Le falta guardar el payload de la petición?** No el payload completo, pero sí algo relacionado y real: revisado el middleware completo (`app/Http/Middleware/Idempotencia.php`), `reproducir()` ya valida que la clave reusada sea del mismo `usuario_id` y el mismo `endpoint` (rechaza con 422 si no) — pero **nunca compara el contenido del request**. Si el mismo usuario reintenta la misma clave en el mismo endpoint con un **cuerpo distinto** (ej. cambia el profesional pedido entre el primer intento fallido y el reintento), el middleware devuelve la respuesta vieja sin avisar del cambio — el cliente cree que se aplicó lo nuevo y en realidad tiene lo viejo. Se acuerda agregar `payload_hash` (hash SHA-256 del cuerpo del request, no el payload completo — evita duplicar datos potencialmente sensibles como `nota_cliente`) para detectar y rechazar ese caso.

### Bloque: `reporte` — polimorfismo con convención pura de Laravel

77. **¿`reporte` debería tener una relación `MorphTo` en vez de resolver `tipo`/`objeto_id` a mano?** Sí — no había ningún bug activo (nada lee esto todavía), pero es una mejora barata: Eloquent ya resuelve esto de forma nativa, no hace falta un `match()` manual. Confirmado que el modelo `Reporte.php` hoy no tiene ningún método que lo resuelva.

78. **¿Debería usarse la convención exacta de Laravel (`objeto_type`/`objeto_id`, cero configuración) o una versión en español (`objeto_tipo`)?** Se discutió el choque con la convención del proyecto (*"código, esquema y dominio en español"* — `objeto_type` sería la única columna con una palabra en inglés en todo el esquema). **Decisión final del usuario: usar la convención pura de Laravel en todas las tablas polimórficas** (`imagen` y `reporte`), priorizando consistencia con el framework sobre la pureza del español para este caso puntual — `tipo` de `reporte` se renombra a `objeto_type`, y la tabla `imagen` (todavía no implementada, ver cambio pendiente) usa `objeto_type` desde el diseño, no `objeto_tipo`. El `Relation::morphMap()` compartido (que traduce el valor guardado en `objeto_type` a la clase real) no cambia con esta decisión — sigue aplicando igual, es independiente del nombre de la columna.

### Bloque: `suscripcion`, `cobro`, `liquidacion`

80. **¿`liquidacion` está bien construida?** Sí, confirmado a fondo — `cerrar()`/`marcarPagada()` verifican el estado antes de transicionar (`if ($liquidacion->estado !== 'borrador')`, etc.), más el lock del periodo (`lockForUpdate()`) ya conocido. Sin hallazgos, sirve de referencia de la rigurosidad esperada en el resto del módulo Billing.

81. **¿`cobro` tiene la misma rigurosidad que `liquidacion`?** No — hallazgo real. `CobroService::marcarPagado()`/`marcarFallido()` no verifican el estado actual antes de transicionar (a diferencia de `liquidacion`, que sí lo hace en cada método). Se podría marcar como pagado un cobro que ya está pagado (**reemitiendo un `comprobante_sri` duplicado** para el mismo pago — problema real de facturación electrónica) o marcar `fallido` un cobro `pagado`. Además, `reembolsado` es un **estado muerto**: está en el `CHECK` del esquema pero no existe ningún `marcarReembolsado()` en todo el código — mismo patrón que `gracia`/`vencida` en `suscripcion` (pregunta 26).

82. **¿Puede un negocio tener dos suscripciones `'activa'` a la vez?** Sí, hoy nada lo impide — mismo patrón de bug exacto que `asignacion` (pregunta 51). `SuscripcionService::activar()` inserta sin verificar si el negocio ya tiene una suscripción activa.

83. **¿`suscripcion.profesionales`/`precio_mensual` se mantienen sincronizados con el personal real del negocio?** No — confirmado que `profesionales` viene directo de lo que mandó el request al activar (`$datos['profesionales']`) y nunca se recalcula después. Un negocio que contrata más personal después de activar Pro sigue pagando el precio viejo indefinidamente, sin que nada lo note. Se resuelve recalculando en el mismo job de vigencia (pregunta 26), en cada renovación de periodo — no con sincronización en tiempo real.

### Bloque: revisión final de las tablas restantes (`local`, `servicio_categoria`, `tamano_mascota`, `resena`, Notifications) — ⚠️ hallazgo de prioridad alta

85. **¿Se invalida el caché del perfil público de `local` al cambiar servicios/amenidades/fotos/horarios/reseñas?** No — hallazgo real. `LocalService::perfilPublico()` cachea 1 hora un perfil que incluye `servicios`, `amenidades`, `fotos`, `horarios`, `resenas`, con el comentario *"invalidado al editar... no hace falta evento de dominio, la invalidación es intra-módulo"* — afirmación **incorrecta**, verificada exhaustivamente: `Cache::forget` de esta clave solo aparece en `LocalService.php`. Ni `ServicioLocalService` (Catalog, agrega/edita servicios), ni `ResenaService` (Reviews), ni los servicios hermanos de amenidad/foto/horario dentro del propio Directory la invalidan. **Consecuencia real:** el perfil público muestra datos desactualizados hasta una hora después de cualquier cambio real — un servicio nuevo, un precio cambiado, una foto subida, una reseña recibida, nada de eso se refleja de inmediato.

86. **¿`servicio_categoria`/`tamano_mascota` necesitan cambios?** No — revisadas, correctas, sin hallazgos.

87. **¿`resena` tiene algún hallazgo propio?** Bien construida (`ResenaService::crear()` guarda correctamente contra duplicados y la ventana de 14 días; `RecalcularScoreRanking` documentado con mucho cuidado, cada peso justificado) — pero confirma el mismo hallazgo de `local`: `ResenaCreada`/`ResenaRespondida` no invalidan ese caché. Nota menor sin cambio: `estado` `en_revision`/`oculta` son estados muertos (sin código que transicione a ellos) — mismo patrón ya visto en `cobro.reembolsado`/`suscripcion.gracia`/`vencida`, consistente con la falta de panel de moderación.

88. **¿El módulo Notifications tiene algún hallazgo?** No — revisado completo (`NotificacionService`, `EnviarNotificacionesProgramadas`, `DeviceTokenService`, los 5 modelos/tablas). Bien construido, incluida la verificación real de que la ventana de silencio (8am-9pm) se aplica donde corresponde (recordatorios, solicitud de reseña) y **no** en notificaciones transaccionales inmediatas — comportamiento correcto, no un vacío. Sin cambios.

89. **¿Debería `activo` (o un `deleted_at` genérico estilo Laravel `SoftDeletes`) existir en todas las tablas?** No — regla ya decidida y documentada en la skill `migracion`: `activo` y `estado` no son intercambiables, y cada tabla usa el mecanismo que corresponde a su naturaleza (booleano simple, máquina de estados, vigencia por fecha, o ninguno para logs/pivotes puros). Un `deleted_at` universal se descartó con motivos concretos de **este** esquema, no en abstracto: rompería los `UNIQUE` (`telefono`, `email`...) sin arreglo automático (requeriría índice parcial por columna de todos modos); competiría con `estado` pudiendo contradecirlo; no lo respetarían las consultas SQL crudo del motor de disponibilidad (`DisponibilidadService`) ni de la búsqueda geoespacial (`BusquedaLocalService`); y para `usuario` específicamente ya existe algo mejor y exigido por LOPDP (`anonimizado_at`, que anonimiza datos reales en vez de solo ocultar la fila). Sin cambio — se confirma dejar el esquema como está en este aspecto.

---

## Cambios pendientes de implementar

### [x] Rediseño de `consentimiento`: catálogo de finalidades + documentos legales versionados

**Implementado 2026-09-29**: `finalidad_consentimiento` y `documento_legal` agregadas, `consentimiento.finalidad`/`documento_version` reemplazadas por `finalidad_id`/`documento_legal_id` (nullable) — editado directo en la migración base de Identity (`2026_01_01_000100_...`), no en una migración nueva, mismo criterio que el resto de esta revisión (sin datos de producción que migrar). `finalidad_consentimiento` suma además `documento_tipo` (nullable, no está en el SQL original de abajo): decide qué tipo de `documento_legal` respalda cada finalidad — necesario porque esa asignación es justo la decisión legal pendiente (pregunta 10), y el esquema tenía que permitirla sin forzarla.

Modelos `FinalidadConsentimiento`/`DocumentoLegal` nuevos (con `DocumentoLegal::vigente($tipo)`); `Consentimiento` gana `finalidadConsentimiento()`/`documentoLegal()`. `OtorgarConsentimiento` resuelve `finalidad_id` por código y `documento_legal_id` por el documento vigente del tipo asignado — con autocuración (mismo patrón que `ImagenService::tipoPerfilId()`) si el catálogo no está sembrado, porque el registro por OTP siempre otorga `operacion_servicio` y no puede fallar por eso. `RevocarConsentimiento`/`ListarConsentimientos`/`ConsentimientoResource` actualizados al nuevo esquema. `ActualizarConsentimientoRequest` valida contra `finalidad_consentimiento` activa (`Rule::exists`), ya no contra un array fijo.

Nuevo endpoint público `GET /finalidades-consentimiento` (sin el `GET /documentos-legales/{tipo}` separado, mencionado como "posible" abajo — no hizo falta, el documento vigente ya viaja embebido). Seeder `FinalidadConsentimientoSeeder` con las 4 filas iniciales, registrado en `DatabaseSeeder`. `config('fullpinta.version_terminos')` eliminado (reemplazado por `documento_legal`). `docs/api-referencia.md` y la especificación (§4.3) actualizados en el mismo cambio.

**Deliberadamente sin resolver** (ver bloque de pendientes no técnicos abajo, sin cambio): `finalidad_consentimiento.documento_tipo` y `documento_legal` quedan sembrados solo con texto `[PENDIENTE-LEGAL]` — ningún `documento_legal` real creado todavía. Reemplazar antes de producción.

**Motivación:** hoy `finalidad` es un código sin texto propio (obliga a hardcodear la UI en el móvil) y no existe ninguna entidad que guarde el contenido real de los documentos legales aceptados — solo un string de versión suelto. Ver preguntas 6–10 arriba.

**Impacto en esquema** (nueva migración, después de `2026_09_17_160634_agregar_google_id_a_usuario.php`):

```sql
CREATE TABLE finalidad_consentimiento (
  id uuid PRIMARY KEY,
  codigo varchar(40) UNIQUE NOT NULL,   -- operacion_servicio, comunicaciones_transaccionales, marketing, transferencia_internacional
  nombre varchar NOT NULL,
  descripcion text NOT NULL,
  obligatorio boolean NOT NULL DEFAULT false,
  orden smallint NOT NULL DEFAULT 0,
  activo boolean NOT NULL DEFAULT true
);

CREATE TABLE documento_legal (
  id uuid PRIMARY KEY,
  tipo varchar(30) NOT NULL,            -- politica_privacidad, politica_marketing, terminos_condiciones
  version varchar(20) NOT NULL,
  contenido text NOT NULL,              -- texto completo, markdown, inmutable una vez publicado
  url varchar,                          -- opcional, página pública equivalente
  vigente_desde timestamptz NOT NULL,
  vigente_hasta timestamptz,
  UNIQUE (tipo, version)
);

ALTER TABLE consentimiento
  ADD COLUMN finalidad_id uuid REFERENCES finalidad_consentimiento(id),
  ADD COLUMN documento_legal_id uuid REFERENCES documento_legal(id);  -- nullable, ver pregunta 10
-- migrar datos existentes de `finalidad` (varchar) -> finalidad_id antes de dropear la columna vieja
-- documento_version (varchar) se reemplaza por documento_legal_id; decidir si se dropea o se deja como legado
```

**Impacto en código (Identity):**
- `Consentimiento` (modelo): agregar relaciones `finalidadConsentimiento()`, `documentoLegal()`.
- `OtorgarConsentimiento`: en vez de escribir el código de finalidad y `config('fullpinta.version_terminos')` a mano, resolver `finalidad_id` por código y `documento_legal_id` por el documento **vigente** del tipo que corresponda a esa finalidad (si existe uno).
- `ActualizarConsentimientoRequest`: la validación `in:...` pasa a validar contra `finalidad_consentimiento` activa, no contra un array fijo en el Request.
- Nuevos casos de uso/endpoints de solo lectura para que el front los consuma:
  - `GET /finalidades-consentimiento` — catálogo completo con su documento vigente embebido, para pintar la pantalla de consentimiento sin hardcodear texto en la app.
  - Posiblemente `GET /documentos-legales/{tipo}` si se necesita consultar versiones históricas por separado.
- Seeder nuevo (`DatabaseSeeder`, patrón `updateOrCreate` como el resto del catálogo) para poblar `finalidad_consentimiento` con las 4 filas iniciales.

**Pendiente de confirmar antes de implementar (no técnico):**
- Qué finalidades llevan documento legal propio y cuáles no (pregunta 10) — validar con asesoría legal LOPDP.
- Si `operacion_servicio`/`comunicaciones_transaccionales`/`transferencia_internacional` comparten un solo documento (`politica_privacidad`) o van separados.
- Texto real de cada `descripcion` y `contenido` — no es un dato técnico, lo da el negocio/legal.

**Documentar al implementar:** actualizar `docs/api-referencia.md` con los endpoints nuevos (regla del proyecto: se documenta en el momento en que se crea el endpoint, no al final).

### [x] Mover `genero`/`fecha_nacimiento` de `cliente_perfil` a `usuario`

**Implementado 2026-09-28**: columnas y `CHECK` movidos en la migración base de `usuario`/`cliente_perfil` (sin datos de producción que migrar). Actualizado el cast en `Usuario`/`ClientePerfil` y las factories correspondientes.

**Motivación:** son atributos de la persona, no del comportamiento como cliente — ver pregunta 13.

```sql
ALTER TABLE usuario ADD COLUMN genero varchar(20);
ALTER TABLE usuario ADD COLUMN fecha_nacimiento date;

-- si hay datos de desarrollo que migrar:
UPDATE usuario u SET genero = cp.genero, fecha_nacimiento = cp.fecha_nacimiento
FROM cliente_perfil cp WHERE cp.usuario_id = u.id;

ALTER TABLE cliente_perfil DROP COLUMN genero;
ALTER TABLE cliente_perfil DROP COLUMN fecha_nacimiento;
```

Mover también el `CHECK` del enum `genero` de `cliente_perfil` a `usuario`. Impacto en código: mínimo, solo el cast en `ClientePerfil` (ningún controller/request/resource los usa todavía).

### [x] Verificación de propiedad del email (`email_verificado`)

**Implementado 2026-09-29**: `usuario.email_verificado boolean default false` agregado. `otp` generalizada para aceptar teléfono O email como destino (XOR, editada en su propia migración con fecha, `2026_09_15_141557_crear_tabla_otp.php`, en vez de una nueva — sin datos de producción que migrar): `telefono` pasa a nullable, se agrega `email`, y el `CHECK` de exclusión mutua.

Se re-evaluó el "bloqueador real" de abajo a mitad de esta misma sesión: no bloqueaba tanto como parecía — ver la nota en el ítem de recuperar contraseña. Puerto nuevo `EnviadorCodigoEmail` (Identity, no Notifications — es un código suelto, no una plantilla) con `LogEnviadorCodigoEmail`/`FakeEnviadorCodigoEmail`, mismo patrón que `EnviadorOtp`. Clase compartida `CodigoVerificacion` (Identity/Application) centraliza generación/verificación de códigos para cualquier flujo que NO sea login/registro por teléfono (que conserva su propia clase, sin tocar) — la usan tanto este ítem como el de recuperar contraseña.

Nuevos casos de uso `SolicitarVerificacionEmail`/`ConfirmarVerificacionEmail` y endpoints `POST /cuenta/email/solicitar-verificacion` + `POST /cuenta/email/verificar` (🔒, en `CuentaController`). `docs/api-referencia.md` y la especificación (§4.3) actualizados.

**Motivación:** ver pregunta 12 — probar que el usuario controla la bandeja de ese correo, no solo que el string es único.

### [x] Agregar `'email'` al enum `notificacion.canal`

**Implementado 2026-09-29**: valor `'email'` agregado al `CHECK` de `notificacion.canal`. Esto es literalmente todo lo que pedía el título del ítem — el productor real de la notificación "suscripción por vencer → Push + email" (§11.2) sigue sin construirse: necesita un `EnviadorEmail` genérico con plantillas (mismo patrón que `EnviadorWhatsApp`/`PlantillaWhatsapp`), que es un componente distinto del `EnviadorCodigoEmail` que sí se construyó (ese envía un código suelto, no una notificación con plantilla). Anotado en `context/plan-implementacion.md` → "Bloqueadores externos" para no perderlo de vista.

**Motivación:** bloquea implementar el evento que la spec documenta explícito en §11.2 ("Suscripción por vencer → Propietario → Push + email"), diferido desde la Fase 9 y nunca resuelto ni siquiera tras completarse la Fase 10 (Billing).

### [x] Recuperar / cambiar contraseña

**Implementado 2026-09-29**: el supuesto "bloqueador real" (`EnviadorEmail` inexistente) resultó menos bloqueante de lo que parecía al escribir este documento — recuperar contraseña por **teléfono vía WhatsApp** no depende de eso en absoluto: reutiliza el mismo `EnviadorOtp` que ya usa el login por OTP (§11.2/§11.3, con SMS de respaldo, real diferido por aprobación de plantillas de Meta — bloqueador ya trackeado, no uno nuevo). El usuario confirmó explícitamente que la recuperación debe ofrecer ambos canales (correo y WhatsApp; SMS queda fuera por ahora), así que se construyeron los dos de una vez, con el mismo patrón `Log`/`Fake` que el resto de puertos externos del proyecto — ver el ítem de verificación de email para el puerto `EnviadorCodigoEmail` nuevo que ambos comparten.

Nuevos casos de uso: `SolicitarRecuperacionContrasena`/`RestablecerContrasena` (canal `whatsapp` o `email`, mismo mensaje genérico exista o no la cuenta — evita confirmar qué destino está registrado) y `CambiarContrasena` (con sesión, conociendo la actual, sin código). `RestablecerContrasena` revoca todas las sesiones existentes (mismo criterio que `AnonimizarUsuario`: recuperar acceso es el escenario en que no se sabe quién más quedó autenticado) y, si el canal fue `email`, marca `email_verificado` de paso. `CambiarContrasena` no revoca nada — no se perdió el acceso.

Endpoints: `POST /auth/contrasena/olvide` + `POST /auth/contrasena/restablecer` (públicos) y `PUT /cuenta/contrasena` (🔒), en el nuevo `ContrasenaController`. De paso se corrigieron dos docblocks desactualizados (`VerificarOtp`, `AnonimizarUsuario`) que todavía decían "esta plataforma solo autentica por OTP" — ya no es cierto desde que existe `IniciarSesionConEmail` (2026-09-17), y quedaron sin actualizar hasta ahora. `docs/api-referencia.md` y la especificación (§4.3) actualizados.

**Pendiente, explícitamente fuera de esta implementación:** canal por SMS (el usuario lo mencionó como ideal a futuro, no para ahora) y el `EnviadorEmail` genérico con plantillas para notificaciones no transaccionales (ítem separado, ver arriba).

**Motivación:** verificado que no existe ningún endpoint de esto — las cuentas registradas por correo+contraseña no tienen forma de recuperar el acceso si la olvidan, más allá del salvavidas indirecto de loguearse por teléfono+OTP (que no permite rotar la contraseña en sí).

### [x] Simplificar `GET /auth/contexto`: quitar `es_cliente` del payload

**Motivación:** ver pregunta 15 — valor constante, nunca varía, no aporta información. Reemplazar por una nota en prosa en `docs/api-referencia.md` ("cualquier cuenta autenticada puede agendar para sí misma, no requiere selección de contexto"). Cambio menor, no urgente — no toca comportamiento, solo limpia el contrato.

**Implementado 2026-09-28**: quitado de `ResolverContexto` (docblock y array devuelto), tests actualizados, `docs/api-referencia.md` actualizado con la nota en prosa.

### [x] Catálogos de `mascota`: `especie_mascota` + `raza_mascota`

**Motivación:** ver preguntas 16–17.

**Implementado 2026-09-28**: tablas `especie_mascota`/`raza_mascota` agregadas en la migración base de Identity (antes de `mascota`), `mascota.especie`/`raza` (varchar) reemplazadas por `especie_id`/`raza_id` (FK, `raza_id` nullable). Modelos `EspecieMascota`/`RazaMascota` nuevos con sus factories; `Mascota` gana relaciones `especie()`/`raza()`. Seeders `EspecieMascotaSeeder` (perro/gato/otro) y `RazaMascotaSeeder` (set inicial por especie, incluye "Mestizo"), registrados en `DatabaseSeeder` tras `TamanoMascotaSeeder`. Sin controller/endpoint de `mascota` que tocar — no existe ninguno todavía en la API. Especificación (`context/fullpinta-especificacion.md` §4.3) actualizada. Suite completa verificada en verde (493 tests).

```sql
CREATE TABLE especie_mascota (
  id uuid PRIMARY KEY,
  codigo varchar(20) UNIQUE NOT NULL,   -- perro, gato, otro (sembrado inicial; se amplía cuando grooming se active)
  nombre varchar NOT NULL,
  icono varchar(60),
  activo boolean NOT NULL DEFAULT true
);

CREATE TABLE raza_mascota (
  id uuid PRIMARY KEY,
  especie_id uuid NOT NULL REFERENCES especie_mascota(id),
  codigo varchar(60) NOT NULL,          -- incluye 'mestizo' como fila normal, por especie
  nombre varchar NOT NULL,
  activo boolean NOT NULL DEFAULT true,
  UNIQUE (especie_id, codigo)
);

ALTER TABLE mascota
  ADD COLUMN especie_id uuid REFERENCES especie_mascota(id),
  ADD COLUMN raza_id uuid REFERENCES raza_mascota(id);
-- migrar datos de la columna especie (varchar) actual, luego dropearla
-- raza (varchar libre) se reemplaza por raza_id
```

**Documentar/sembrar al implementar:** seeder con `perro`/`gato`/`otro` (especie) y un set inicial modesto de razas comunes + `"Mestizo"`/`"Sin raza definida"` por especie — sin exhaustividad, se amplía después con `INSERT`.

### [x] Consolidar imágenes: catálogo `tipo_imagen` + tabla polimórfica `imagen`

**Implementado 2026-09-28**: `tipo_imagen`/`imagen` en migración nueva (`2026_01_01_000050`, antes de Identity — `usuario.foto_perfil_id` ya necesita la FK). `local_foto`/`profesional_foto` eliminadas; `usuario.foto_url`/`profesional.foto_url` reemplazadas por `foto_perfil_id` (FK nullable, propia e independiente entre `usuario` y `profesional`); `mascota.foto_perfil_id` agregada (sin galería ni endpoint todavía — igual criterio que sus catálogos de especie/raza: se modela el dato, no hay controller que lo use aún). Sin datos que migrar (sin producción). `Relation::morphMap()` registrado en `AppServiceProvider` (`local`, `profesional`, `mascota`, `usuario`) — **efecto colateral real y esperado**: aplica a *cualquier* relación morph de esos modelos en toda la app, incluida `tokens()` de Sanctum sobre `Usuario` (`personal_access_tokens.tokenable_type` pasa de `App\Models\Usuario` a `'usuario'`); `TokenSanctumTest` actualizado para reflejar la nueva convención, no es una regresión.

`ImagenController`/`ImagenService`/`ImagenResource`/los `Request` de creación y actualización quedaron **compartidos de verdad** (no uno por módulo): viven fuera de cualquier módulo (`app/Http/Controllers`, `app/Support`, `app/Http/Requests`, `app/Http/Resources`) porque ningún módulo es dueño único de la galería — mismo criterio que los `Policies` ya compartidos. `index`/`store` van por dueño (`locales/{local}/imagenes`, `profesionales/{profesional}/imagenes`); `update`/`destroy` son planas (`imagenes/{imagen}`) y se registran **una sola vez** (en `Directory/routes.php`) para no duplicar la misma URI. La invalidación del caché de perfil público del local (que antes vivía inyectada en `LocalFotoService`) se resolvió con el mismo patrón de evento de dominio ya usado en la sesión: `ImagenService` dispara `App\Events\ImagenModificada` (evento sin módulo dueño, igual que el servicio) y `Directory\Listeners\InvalidarCachePerfilPublico` lo escucha, filtrando por `objeto_type === 'local'` — cero import cruzado entre `Support` y `Directory`.

`ProfesionalService::crearConAsignacion()`/`actualizar()` interceptan `foto_url` del request y lo enrutan por `ImagenService::establecerFotoPerfil()` en vez de guardarlo como columna; mismo patrón en `IniciarSesionConGoogle` (foto de Google) y `AnonimizarUsuario` (limpia el puntero, no borra la imagen histórica). El contrato de API no cambia: `foto_url` se sigue viendo en `UsuarioResource`/`ProfesionalResource`/`ProfesionalPublicoResource`, ahora derivado de `->fotoPerfil?->url` (requiere la relación precargada explícitamente en cada punto de construcción del Resource — sin `whenLoaded`, a propósito, para que `preventLazyLoading` siga cazando el N+1 en vez de devolver el campo ausente en silencio).

Endpoints renombrados/consolidados: `GET|POST /locales/{local}/fotos` → `.../imagenes`, `GET|POST /profesionales/{profesional}/fotos` → `.../imagenes`, `PATCH|DELETE /fotos/{foto}` y `/profesionales/{profesional}/fotos/{foto}` → un solo `PATCH|DELETE /imagenes/{imagen}`. El portafolio del profesional ya no acepta `tipo` en el body (se fuerza `'muestra'` en el servidor, igual que antes no tenía columna `tipo`). `docs/api-referencia.md` y `context/fullpinta-especificacion.md` actualizados. Tests renombrados/reescritos (`LocalImagenTest`, `ProfesionalImagenTest`) más un fix a `ModelosTest` (su chequeo genérico de relaciones no contemplaba `MorphTo`, primera vez que aparece en el proyecto). Suite completa verificada en verde (505 tests).

**Motivación:** ver preguntas 18–20. Reemplaza `local_foto`, `profesional_foto`, `usuario.foto_url`, `profesional.foto_url`. Agrega capacidad de fotos a `mascota` (no tenía ninguna).

```sql
CREATE TABLE tipo_imagen (
  id uuid PRIMARY KEY,
  codigo varchar(20) UNIQUE NOT NULL,   -- perfil, portada, fachada, interior, muestra
  nombre varchar NOT NULL,
  icono varchar(60),
  orden smallint NOT NULL DEFAULT 0,
  activo boolean NOT NULL DEFAULT true
);

CREATE TABLE imagen (
  id uuid PRIMARY KEY,
  objeto_type varchar(20) NOT NULL,     -- 'local','profesional','mascota','usuario'... — vía Relation::morphMap(), nunca el FQCN. Convención pura de Laravel (uuidMorphs), no objeto_tipo.
  objeto_id uuid NOT NULL,
  tipo_id uuid NOT NULL REFERENCES tipo_imagen(id),
  url varchar NOT NULL,
  orden smallint NOT NULL DEFAULT 0,
  created_at timestamptz NOT NULL
);
CREATE INDEX imagen_objeto ON imagen (objeto_type, objeto_id);

ALTER TABLE usuario ADD COLUMN foto_perfil_id uuid REFERENCES imagen(id);
ALTER TABLE mascota ADD COLUMN foto_perfil_id uuid REFERENCES imagen(id);
ALTER TABLE profesional ADD COLUMN foto_perfil_id uuid REFERENCES imagen(id);   -- propia, independiente de usuario.foto_perfil_id (pregunta 20b)
-- local NO lleva puntero: se queda como galería pura (fachada/interior/muestra)
-- usuario.foto_url y profesional.foto_url se dropean, se derivan de la relación (->fotoPerfil?->url)
```

**Impacto en código:**
- Migrar datos existentes de `local_foto` (`objeto_type='local'`, `tipo_id` resuelto a 'fachada'/'interior'/'muestra') y `profesional_foto` (`objeto_type='profesional'`, `tipo_id`='muestra') antes de borrarlas.
- `Local`/`Profesional`/`Mascota`/`Usuario`/`Negocio`/`Producto`: relación `imagenes()` con convención pura de Laravel, cero configuración (`return $this->morphMany(Imagen::class, 'objeto');` — Eloquent infiere `objeto_type`/`objeto_id` solo) + `fotoPerfil()` donde aplique.
- Consolidar `LocalFotoController`/`LocalFotoResource` y sus equivalentes de Staffing en un solo `ImagenController`/`ImagenService` compartido.
- Actualizar los tests existentes de Fase 3 y Fase 4 que hoy asumen `local_foto`/`profesional_foto`.
- Riesgo a cuidar en el `ImagenService`: la FK de `foto_perfil_id` no garantiza que la imagen referenciada sea del mismo dueño — el servicio debe escribir ambas cosas (subir + apuntar el puntero) en una sola transacción.

**Resuelto** (pregunta 20/20b): `local` sin puntero (solo galería); `negocio` con `foto_perfil_id` + `portada_imagen_id` (ver bloque de `negocio` más abajo); `profesional` con `foto_perfil_id` propio, independiente del de `usuario`.

### [x] `plan`: agregar límites/capacidades por feature y coeficientes de precio

**Motivación:** ver preguntas 21b–21d.

**Implementado 2026-09-28**: columnas agregadas a `plan` en la migración base (límites nullable=ilimitado, capacidades booleanas, `precio_base`/`precio_adicional`/`meses_pago_anual`/`orden`), sembradas con los valores reales de §9.4/§9.6 en `PlanSeeder`. `SuscripcionService::activar()`/`montoDelCiclo()` leen `$plan->precio_base`/`precio_adicional`/`meses_pago_anual` en vez de las constantes PHP fijas (`montoDelCiclo()` hace `loadMissing('plan')`, sin pasarle la carga al llamador). `LiquidacionResource` deja de usar `Negocio::esPro()` como proxy para el desglose y lee `plan->liquidacion_desglose` directo (`esPro()` se conserva tal cual — sigue siendo válido como "¿está en Pro ahora mismo?" para los demás usos, p. ej. `SuscripcionTest`); `LiquidacionController` ahora hace `->load('local.negocio.plan')`. `PlanFactory` gana un state `pro()` con los valores reales, usado por `NegocioFactory::pro()` y por `SuscripcionTest` para no depender de los defaults (que son los de Free). No se activa ningún enforcement de límites — solo modela los datos, según lo acordado. Suite completa verificada en verde (493 tests).

```sql
ALTER TABLE plan
  ADD COLUMN limite_locales smallint,               -- NULL = ilimitado
  ADD COLUMN limite_profesionales smallint,         -- NULL = ilimitado
  ADD COLUMN limite_fotos smallint,                 -- NULL = ilimitado
  ADD COLUMN liquidacion_desglose boolean NOT NULL DEFAULT false,
  ADD COLUMN recordatorios_whatsapp boolean NOT NULL DEFAULT false,
  ADD COLUMN responder_resenas boolean NOT NULL DEFAULT false,
  ADD COLUMN estadisticas_completas boolean NOT NULL DEFAULT false,
  ADD COLUMN promociones_horas_valle boolean NOT NULL DEFAULT false,
  ADD COLUMN bloque_destacados boolean NOT NULL DEFAULT false,
  ADD COLUMN precio_base numeric(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN precio_adicional numeric(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN meses_pago_anual smallint NOT NULL DEFAULT 12,   -- 10 = paga 10, se lleva 12
  ADD COLUMN orden smallint NOT NULL DEFAULT 0;
```

**Impacto en código:**
- `Negocio::esPro()` dejaría de comparar `$this->plan->codigo === 'pro'` — cada capacidad se lee directo de su columna (`$this->plan->liquidacion_desglose`, etc.) en vez de usar el código del plan como proxy.
- `SuscripcionService::activar()`: reemplazar `PRECIO_BASE_USD`/`PRECIO_ADICIONAL_USD`/`MESES_PLAN_ANUAL` (constantes PHP) por `$plan->precio_base`/`$plan->precio_adicional`/`$plan->meses_pago_anual` — corrige de paso el bug latente de que la fórmula no distinguía el plan al que se aplicaba.
- Seeder de `plan` (Fase 1) actualizado con los valores reales de la tabla §9.4 para `free`/`pro`.

**Opcional, a confirmar con el usuario:** `suscripcion.descuento_motivo varchar(120) nullable` — trazabilidad de por qué el precio real de un negocio se apartó del default calculado (mismo criterio de "si es plata, se documenta por qué" ya aplicado en `Liquidacion`).

**No se toca:** el enforcement real de estos límites (bloquear crear un 2º local en Free, etc.) sigue diferido a propósito — coincide con la decisión ya tomada en Fase 10 (§9.7: "todo gratis los primeros ~6 meses"). Este cambio solo modela los datos, no activa ningún bloqueo.

### [x] Job de vigencia de suscripción: renovación, cancelación diferida y notificación de vencimiento

**Implementado 2026-09-28**: nuevo job `Billing\Jobs\ActualizarVigenciaSuscripciones` (diario 03:00, cola `batch`, mismo patrón que `RecalcularScoreRanking`), registrado en `bootstrap/app.php`. `SuscripcionService::cancelar()` deja de bajar el negocio a Free de inmediato — solo marca `estado='cancelada'`; el downgrade real lo hace el job cuando `vigente_hasta` se cumple (respeta el período ya pagado). Nuevo `SuscripcionService::bajarNegocioAFree()` (único camino para ese downgrade, con la misma autocuración del plan `free` que ya tenía `cancelar()`). El job cubre las tres transiciones automáticas: `activa` vencida → registra `Cobro` pendiente + pasa a `gracia`; `gracia` que superó `config('fullpinta.suscripcion.dias_gracia')` (10 días, nuevo) → `vencida` + downgrade; `cancelada` cuyo período ya se cumplió → downgrade sin cobro nuevo. **No implementado a propósito**: la notificación "por vencer" a los 7 días — depende del canal `email`/`EnviadorEmail`, bloqueador ya registrado aparte; se omite en vez de dejar un no-op. Tampoco se construyó la reactivación automática de una suscripción en `gracia` cuando su cobro se marca pagado — no estaba en el alcance de este ítem y merece su propia decisión de diseño. Test de `SuscripcionTest` actualizado a la nueva semántica de `cancelar()`; suite nueva `ActualizarVigenciaSuscripcionesTest` (5 casos). Suite completa verificada en verde (517 tests; una corrida intermedia mostró el flake ya conocido de `ConcurrenciaCitaTest` bajo carga del sistema, confirmado no relacionado corriéndolo solo).

**Motivación:** ver preguntas 26–27. Une dos correcciones relacionadas: activar el ciclo de renovación (hoy inexistente) y corregir que cancelar respete el período ya pagado.

**Diseño** (job diario, cola `batch`, mismo patrón que `ExpirarHoldsVencidos`/`RecalcularScoreRanking`):

```
Para cada `suscripcion` con `vigente_hasta` <= hoy + 7 días:

  si faltan exactamente 7 días y estado == 'activa'
    → disparar notificación "suscripción por vencer" (depende del canal 'email' + EnviadorEmail, ya pendiente — pregunta 11)

  si vigente_hasta <= hoy:
    si estado == 'cancelada'
      → bajar negocio a Free ahora (plan_id, plan_vigente_hasta = null); NO se genera cobro nuevo
    si estado == 'activa'  (nunca canceló, el período simplemente se cumplió)
      → CobroService::registrar() para el siguiente período
      → estado -> 'gracia'
    si estado == 'gracia' y ya pasó el margen de tolerancia sin pago (10 días, ver config)
      → estado -> 'vencida', bajar negocio a Free
```

**Margen de gracia confirmado con el usuario: 10 días.** Va en `config/fullpinta.php`, no en la base — mismo criterio ya usado para `otp`/`login` (decisión de implementación ajustable por el equipo técnico, no varía por plan ni por negocio):

```php
// config/fullpinta.php
'suscripcion' => [
    'dias_gracia' => env('FULLPINTA_DIAS_GRACIA_SUSCRIPCION', 10),
],
```

**Cambios de código:**
- `SuscripcionService::cancelar()`: **quitar** el `update` inmediato de `negocio.plan_id`/`plan_vigente_hasta` — solo marca `estado='cancelada'`. El downgrade real lo hace el job, cuando `vigente_hasta` se cumpla de forma natural (no antes) — así se respeta el período ya pagado, sea mensual o anual.
- Nuevo job + registrarlo en `bootstrap/app.php` (`->withSchedule(...)`).
- `CobroService::registrar()` pasa de código huérfano a tener un llamador real.
- Nuevo endpoint o confirmación de que no hace falta uno para crear el cobro (hoy no existe ninguno — se creaba solo por este job).

### [x] `negocio`: imágenes (logo de marca) + separar verificación de RUC de la verificación de local

**Implementado 2026-09-28**: `foto_perfil_id`/`portada_imagen_id` (FK nullable a `imagen`) y `ruc_verificado`/`ruc_verificado_at` agregados a `negocio` en la migración base; `'negocio'` sumado al `Relation::morphMap()`. `Local::estaVerificado()` (nuevo) combina `local.verificado && negocio.ruc_verificado` — reemplaza la lectura directa de `local.verificado` en `LocalResource`/`LocalPublicoResource`/`RecalcularScoreRanking` (bonus); `BusquedaLocalService` lo computa en SQL (`JOIN negocio` + `(l.verificado AND n.ruc_verificado) as verificado`), mismo criterio en las tres superficies públicas. `NegocioResource` expone `ruc_verificado`/`ruc_verificado_at`/`logo_url`/`portada_url` (derivados de `fotoPerfil`/`portadaImagen`), de solo lectura — sin flujo de verificación ni de subida de logo todavía, mismo alcance pendiente que `reporte`/`solicitud_catalogo`. `LocalFactory::verificado()`/`NegocioFactory::rucVerificado()` (nuevo) actualizados para que el estado de factory siga significando "de verdad verificado", no solo la columna del local. **De paso**, un bug real encontrado por el propio test nuevo: `NegocioService::crear()` no fijaba `ruc_verificado` explícito — quedaba `null` en memoria hasta un `fresh()` (la trampa de `DEFAULT` de Postgres documentada en la skill `migracion`), corregido. Tests nuevos en `LocalTest`, `PerfilPublicoLocalTest`, `BusquedaLocalTest`, `NegocioTest`; `RecalcularScoreRankingTest` actualizado para seguir dando el bono correctamente. Suite completa verificada en verde (509 tests).

**Motivación:** ver preguntas 29–30.

```sql
ALTER TABLE negocio
  ADD COLUMN foto_perfil_id uuid REFERENCES imagen(id),      -- logo de marca
  ADD COLUMN portada_imagen_id uuid REFERENCES imagen(id),   -- portada
  ADD COLUMN ruc_verificado boolean NOT NULL DEFAULT false,
  ADD COLUMN ruc_verificado_at timestamptz;
```

**Impacto en código:**
- `foto_perfil_id` depende de que exista `imagen`/`tipo_imagen` (cambio pendiente ya registrado más arriba) — agregar `'negocio'` al `Relation::morphMap()` junto a `local`/`profesional`/`mascota`/`usuario`.
- El badge público de "verificado" (`LocalResource`/`LocalPublicoResource`/`BusquedaLocalResource`, y el bonus de `RecalcularScoreRanking`) pasa a leer `negocio.ruc_verificado && local.verificado`, no solo `local.verificado`.
- **Sigue sin existir ningún flujo para verificar nada** (ni RUC ni local) — construir el endpoint/panel para esto es trabajo aparte, no incluido en este cambio de esquema, mismo alcance pendiente que `solicitud_catalogo`/moderación de `reporte`.

### [x] Extraer `metodo_pago` como catálogo compartido (reemplaza la categoría `pago` de `amenidad` y el `CHECK` de `cita`)

**Motivación:** ver pregunta 34.

**Implementado 2026-09-28**: tablas `metodo_pago`/`local_metodo_pago` agregadas en la migración base de Directory; `cita.metodo_pago` (varchar+CHECK) reemplazada por `metodo_pago_id` (FK nullable) en la migración de Scheduling. Modelo `MetodoPago` nuevo (tabla de parámetros) con relaciones `Local::metodosPago()`/`Cita::metodoPago()`. Categoría `pago` y sus 4 amenidades quitadas de `AmenidadCategoriaSeeder`/`AmenidadSeeder`; `MetodoPagoSeeder` nuevo (efectivo/transferencia/tarjeta/payphone), registrado en `DatabaseSeeder`. `CitaResource` expone `metodo_pago_id` (no había ningún punto en `CitaService` que escribiera el campo viejo — queda igual de sin uso que antes, solo cambia el tipo de dato). Sin controller/endpoint para `local_metodo_pago` todavía — mismo criterio que los catálogos de mascota: se modela el dato, el endpoint queda para cuando se necesite. Conteos hardcodeados de `Amenidad` actualizados (27→23) en `SeedersTest`/`CatalogoTest`, con conteo nuevo de `MetodoPago` agregado a `SeedersTest`. Especificación y `docs/api-referencia.md` actualizados. Suite completa verificada en verde (497 tests).

```sql
CREATE TABLE metodo_pago (
  id uuid PRIMARY KEY,
  codigo varchar(20) UNIQUE NOT NULL,   -- efectivo, transferencia, tarjeta, payphone
  nombre varchar NOT NULL,
  icono varchar(60),
  orden smallint NOT NULL DEFAULT 0,
  activo boolean NOT NULL DEFAULT true
);

CREATE TABLE local_metodo_pago (        -- reemplaza las filas de la categoría 'pago' en local_amenidad
  local_id uuid NOT NULL REFERENCES local(id) ON DELETE CASCADE,
  metodo_pago_id uuid NOT NULL REFERENCES metodo_pago(id),
  PRIMARY KEY (local_id, metodo_pago_id)
);

ALTER TABLE cita ADD COLUMN metodo_pago_id uuid REFERENCES metodo_pago(id);
-- migrar datos del varchar viejo (metodo_pago), luego dropearlo
```

**Impacto en código:**
- Migrar las 4 filas de `amenidad` (categoría `pago`) y las filas de `local_amenidad` que las usan, hacia `metodo_pago`/`local_metodo_pago`; eliminar la categoría `pago` de `amenidad_categoria`.
- `CitaResource`/`CitaService`: leer/escribir `metodo_pago_id` en vez del `varchar`.
- **Opcional, a evaluar:** validar en `CitaService` que el `metodo_pago_id` elegido esté entre los que el local aceptó en `local_metodo_pago` — hoy no existe ninguna validación de esto, ni con el diseño viejo ni con el nuevo, así que es una mejora aparte, no un requisito para este cambio de esquema.

### [x] Renombrar `vertical` → `rubro` en todo el proyecto

**Implementado 2026-09-28**: renombrado completo siguiendo el orden obligatorio de la lista — especificación primero, luego migraciones (tabla `vertical` → `rubro`, `servicio_categoria.vertical_id`/`solicitud_catalogo.vertical_id` → `rubro_id`), modelos (`Vertical.php` → `Rubro.php`, relaciones `vertical()` → `rubro()` en `ServicioCategoria`/`SolicitudCatalogo`), seeders (`VerticalSeeder` → `RubroSeeder`), factories (`VerticalFactory` → `RubroFactory`), casos de uso (`SolicitudCatalogoService`, `ServicioCategoriaService`, `CatalogoServicioService`, `BusquedaLocalService` con su SQL crudo), HTTP (`CatalogoController`, `CrearSolicitudCatalogoRequest`, `BuscarLocalesRequest`, los tres `Resource` que exponían el campo), tests (`BusquedaLocalTest`, `ConcurrenciaCitaTest`, `SolicitudCatalogoTest`, `SeedersTest`, `CatalogoTest`, más `ServicioLocalTest`/`PrecioPorTamanoMascotaTest` de ítems ya cerrados que quedaron con el nombre viejo pendiente), y documentación (`docs/api-referencia.md`, skill `migracion`, `PUESTA-EN-MARCHA.md`). El input/output de la API cambió de `vertical` a `rubro` en los tres endpoints que lo exponían (`GET /catalogo/categorias`, `GET /catalogo/servicios`, `GET /buscar/locales`, `POST /locales/{local}/solicitudes-catalogo`) — sin dato de producción que migrar. `context/plan-implementacion.md` se dejó sin tocar a propósito (registro histórico). De paso, `docs/api-referencia.md` ganó `solicitante_id` en la respuesta de `solicitud_catalogo`, que faltaba desde ese ítem anterior. Regenerados los docblocks de `_ide_helper_models.php`. Suite completa verificada en verde (518 tests; una corrida intermedia repitió el flake ya conocido de `ConcurrenciaCitaTest` bajo carga del sistema — confirmado no relacionado corriéndolo solo).

**Motivación:** ver pregunta 38. Decisión de nomenclatura del usuario, no una corrección de un error — el término viejo no estaba mal, se prefiere el nuevo por más claro en español.

**Orden obligatorio (regla del proyecto: la especificación manda, se corrige primero).**

**Verificado con búsqueda exhaustiva en todo el proyecto (no solo en la migración): 37 archivos mencionan "vertical".** Lista completa por categoría, para no dejar ninguno a mitad de camino:

1. **Fuente de verdad, primero que nada:**
   - `context/fullpinta-especificacion.md` — toda mención de "vertical"/"verticales" (§4.5, §8, §16, resumen de alcance v1, y el resto de apariciones)

2. **Esquema/migraciones:**
   - `database/migrations/2026_01_01_000300_crear_esquema_catalog.php` — tabla `vertical` → `rubro`; `servicio_categoria.vertical_id` → `rubro_id`; `solicitud_catalogo.vertical_id` → `rubro_id`
   - `database/migrations/2026_01_01_000200_crear_esquema_directory.php` — revisar por qué aparece (verificar referencia real antes de tocar)
   - Esquema base sin datos de producción → editar migraciones originales + `migrate:fresh`, mismo criterio que las correcciones retroactivas de Fase 3, sin migración nueva encima

3. **Modelos:** `Vertical.php` → `Rubro.php`, `ServicioCategoria.php`, `SolicitudCatalogo.php`, `CatalogoServicio.php` (relaciones `vertical()` → `rubro()`), `Amenidad.php` (solo comentario de docblock, sin columna real)

4. **Seeders:** `VerticalSeeder` → `RubroSeeder`, `ServicioCategoriaSeeder.php`, `CatalogoServicioSeeder.php`, `AmenidadSeeder.php` (comentario), `DatabaseSeeder.php`, `DemoDataSeeder.php`

5. **Factories:** `VerticalFactory.php` → `RubroFactory.php`, `ServicioCategoriaFactory.php`, `SolicitudCatalogoFactory.php`

6. **Casos de uso (`Application/`):** `SolicitudCatalogoService.php`, `ServicioCategoriaService.php`, `BusquedaLocalService.php` (SQL crudo: `sc.vertical_id = :vertical_id` → `sc.rubro_id = :rubro_id`), `CatalogoServicioService.php`

7. **HTTP:** `CatalogoController.php`, `CrearSolicitudCatalogoRequest.php`, `BuscarLocalesRequest.php` (el filtro `vertical` de la búsqueda pública → `rubro`), `ServicioCategoriaResource.php`, `CatalogoServicioResource.php`, `SolicitudCatalogoResource.php`

8. **Tests:** `BusquedaLocalTest.php`, `ConcurrenciaCitaTest.php` (confirmar por qué referencia esto antes de tocar), `SolicitudCatalogoTest.php`, `SeedersTest.php`, `CatalogoTest.php`

9. **Documentación del propio repositorio:**
   - `docs/api-referencia.md` — todo endpoint que exponga `vertical`/`vertical_id` (regla del proyecto: se documenta en el momento, no al final)
   - `.claude/skills/migracion/SKILL.md` — menciona "vertical" como ejemplo vivo del patrón de tabla de parámetros, actualizar para no enseñar el nombre viejo
   - `PUESTA-EN-MARCHA.md` — revisar mención

10. **NO tocar (registro histórico, no instrucción activa):** `context/plan-implementacion.md` — documenta decisiones ya tomadas en su momento con el nombre que existía entonces; reescribir el historial le quita valor de traza. Se deja como está, con una nota si hace falta aclarar que el nombre cambió después.

**Nota:** cambio de alcance amplio — tocar antes de que haya datos reales en producción (que es el caso hoy) evita cualquier migración de datos, solo renombrado de esquema y código.

### [x] `CitaService`: aplicar precio/duración por tamaño de mascota (`servicio_local_tamano`)

**Implementado 2026-09-28**: nueva clase `App\Modules\Scheduling\Application\ResolucionPrecioServicio` (compartida por `CitaService` y `DisponibilidadService`) resuelve precio/duración por `(servicio_local_id, tamano_id)` si existe fila, si no cae al plano de `servicio_local`. `CitaService::crear()` resuelve `mascota->tamano_id` y usa esos valores para cada `cita_item`, `precio_total` y la duración total (el `buffer_min` sigue siendo siempre el plano — `servicio_local_tamano` no tiene columna propia). `DisponibilidadService::slots()` gana un parámetro opcional `mascotaId` (nuevo query param `mascota_id` en `GET /locales/{local}/disponibilidad`, documentado en `docs/api-referencia.md`) para que el slot muestre la ventana real antes de agendar. Agregada también la validación pendiente de `ServicioLocalService::sincronizarTamanos()`: rechaza con 422 si el servicio no pertenece a la vertical `mascotas` (usa el nombre de relación actual `vertical`, no `rubro` — ese rename es un ítem aparte todavía pendiente). Tests nuevos en `tests/Feature/Scheduling/PrecioPorTamanoMascotaTest.php` (cita_item por tamaño, plano sin mascota, ventana de disponibilidad) y en `ServicioLocalTest.php` (rechazo fuera de la vertical mascotas, fixture del test existente corregido para usar la vertical correcta). Suite completa verificada en verde (501 tests).

**Motivación:** ver pregunta 40. Bloqueador funcional para activar la vertical `mascotas` (§16.6) — no urgente mientras siga apagada, pero debe resolverse antes.

**Diseño:**
- En `CitaService::cargarServicios()` (o donde se arme cada `cita_item`): si la cita tiene `mascota_id`, resolver `mascota->tamano_id` y buscar una fila en `servicio_local_tamano` para `(servicio_local_id, tamano_id)`.
- Si existe esa fila → usar su `precio`/`duracion_min` en el `cita_item`, no el plano de `servicio_local`.
- Si no existe (sin `mascota_id`, o el servicio no varía por tamaño) → usar el precio/duración plano de `servicio_local` como hoy (coincide con la interpretación de `precio_desde` como valor "desde $X" de exhibición cuando sí varía por tamaño).
- Revisar también `DisponibilidadService`/el cálculo de duración total de la cita (`sum(duracion_min) + max(buffer_min)`), que hoy también asume la duración plana — debe usar la misma resolución por tamaño si aplica.
- Cubrir con test: agendar el mismo servicio para mascotas de dos tamaños distintos en el mismo local, verificar que el precio/duración congelados en `cita_item` difieren según corresponda.

**No incluido en este cambio** (pregunta 41): el mecanismo de "promociones y horas valle" — no tiene definición de producto todavía, se diseña cuando el negocio especifique la regla real.

**Agregado tras revisar `ServicioLocalTamano.php`:** `ServicioLocalService::sincronizarTamanos()` (el método real que crea estas filas hoy, confirmado en el código) no valida absolutamente nada antes de guardar — hoy se le podría asignar precio por tamaño de mascota a un corte de pelo de barbería, sin que nada lo impida. No puede resolverse con un `CHECK` de Postgres (la cadena `servicio_local → catalogo_servicio → servicio_categoria → rubro` cruza tres FKs, y ya se descartó a propósito en Fase 3 duplicar el rubro directamente en `catalogo_servicio` para facilitar justo este tipo de chequeo). Va como validación de aplicación, en el mismo método:

```php
public function sincronizarTamanos(ServicioLocal $servicio, array $tamanos): Collection
{
    if ($tamanos !== [] && $servicio->catalogoServicio->categoria->rubro->codigo !== 'mascotas') {
        throw_validacion('Este servicio no pertenece al rubro mascotas; no admite precio por tamaño.', 'tamanos');
    }

    $servicio->tamanos()->delete();
    // ... resto igual
}
```

### [x] `solicitud_catalogo`: registrar quién la solicitó

**Motivación:** ver pregunta 43.

**Implementado 2026-09-28**: agregada `solicitante_id` (FK a `usuario`) en la migración base, relación `solicitante()` en el modelo, `SolicitudCatalogoService::crear()` ahora recibe el `Usuario` autenticado y lo guarda; expuesto en `SolicitudCatalogoResource`.

```sql
ALTER TABLE solicitud_catalogo
  ADD COLUMN solicitante_id uuid NOT NULL REFERENCES usuario(id);   -- mismo criterio de nombre que reporte.reportante_id
```

**Impacto en código:** `SolicitudCatalogoService::crear()` pasa a recibir también el `Usuario` autenticado (ya disponible en el controller vía `$request->user()`) y lo guarda como `solicitante_id`. `motivo_rechazo` se queda exactamente como está, sin generalizar — ver pregunta previa sobre eso.

### [x] `producto`: descripción, foto y visibilidad pública vía el perfil del local

**Implementado 2026-09-28**: `descripcion` (text) y `foto_id` (FK nullable a `imagen`) agregados a `producto`; `'producto'` sumado al `Relation::morphMap()`. `ProductoService` gana el mismo patrón `foto_url` → `ImagenService::establecerFotoPerfil()` ya usado para `profesional`/`usuario` (con el 4º parámetro `$columna` nuevo en `ImagenService`, porque `producto.foto_id` no se llama `foto_perfil_id` como los demás). `LocalService::perfilPublico()` precarga `productos` (`activo: true`) + `productos.foto`; nuevo evento `Catalog\Events\ProductoModificado` (mismo patrón que `ServicioLocalModificado`) invalida el caché del perfil público al crear/editar/desactivar un producto. `ProductoController` sin cambios, como anticipaba el plan. **De paso**, un bug real encontrado por el test nuevo: `ImagenService::establecerFotoPerfil()` asumía que `tipo_imagen` código `'perfil'` siempre existe — en un test/dev DB sin sembrar (sin `TipoImagenSeeder`) fallaba con `NOT NULL violation` en `tipo_id`; corregido con el mismo patrón de autocuración ya usado para `NotificacionCategoria`/plan `free`. Este bug ya existía desde el ítem de consolidación de `imagen` (afecta también a `profesional`/`usuario`), simplemente ningún test anterior había ejercitado ese camino end-to-end. Tests nuevos en `ProductoTest`, `PerfilPublicoLocalTest`. Suite completa verificada en verde (512 tests).

**Motivación:** ver preguntas 45–47.

```sql
ALTER TABLE producto
  ADD COLUMN descripcion text,
  ADD COLUMN foto_id uuid REFERENCES imagen(id);
```

**Impacto en código:**
- Agregar `'producto'` al `Relation::morphMap()` del sistema `imagen`, junto a `local`/`profesional`/`mascota`/`usuario`/`negocio`.
- `LocalService::perfilPublico()` (y su Resource) incluye los `producto` **activos** del local (nombre, descripción, precio, foto vía `foto_id`) — mismo patrón de caché e invalidación ya usado para servicios/amenidades/fotos.
- `ProductoController` (gestión, staff) **sin cambios** — sigue exigiendo `rolEnLocal`, es un endpoint distinto del de lectura pública.
- Sin catálogo maestro (pregunta 45) y sin flujo de compra — solo exhibición informativa.

### [x] Eliminar `profesional.independiente` (duplica `asignacion.modalidad`)

**Motivación:** ver pregunta 49.

**Implementado 2026-09-28**: columna quitada de la migración base y de `CrearProfesionalRequest`/`ActualizarProfesionalRequest`/`ProfesionalResource`/`ProfesionalService`/`ProfesionalController`, tests actualizados.

```sql
ALTER TABLE profesional DROP COLUMN independiente;
```

**Impacto en código:** quitar `independiente` de `CrearProfesionalRequest`, `ActualizarProfesionalRequest`, `ProfesionalResource`, `ProfesionalService`, `ProfesionalController` (`->only([...])`). Bajo riesgo — confirmado que hoy no alimenta ninguna lógica de negocio, solo se expone/almacena.

### [x] `asignacion`: impedir dos asignaciones vigentes a la vez para el mismo (profesional, local)

**Implementado 2026-09-28**: índice `asignacion_una_vigente` agregado en la migración base de Staffing; `AsignacionService::crear()` envuelto en `DB::transaction()` (SAVEPOINT) y captura `23505`; `AsignacionService::terminar()` ahora también cierra los `turno` vigentes de esa asignación. Tests nuevos: `AsignacionTest::test_no_se_puede_tener_dos_asignaciones_vigentes...`, `DisponibilidadTest::test_un_profesional_sin_asignacion_vigente_no_ofrece_slots...`.

**Motivación:** ver pregunta 51.

```sql
CREATE UNIQUE INDEX asignacion_una_vigente
  ON asignacion (local_id, profesional_id)
  WHERE hasta IS NULL;
```

**Impacto en código:**
- `AsignacionService::crear()`: capturar `QueryException` con SQLSTATE `23505` (violación de `UNIQUE`, no `23P01` que es solo para `EXCLUDE`) y traducirla con `throw_validacion()` — mismo patrón ya usado en `TurnoService` para su propio constraint.
- Flujo esperado sin cambios: para reemplazar una asignación, primero `terminar()` la vigente (poner `hasta`), luego `crear()` la nueva — ya es el flujo documentado, ahora queda forzado por la base.
- Cubrir con test: crear una asignación vigente, intentar crear una segunda para el mismo par sin terminar la primera, verificar 422.

### [x] ⚠️ PRIORIDAD ALTA — `DisponibilidadService` debe verificar `asignacion` vigente, no solo `turno`

**Implementado 2026-09-28** (junto con el cambio de arriba, mismo commit lógico): `ventanasTurno()` verifica `Asignacion::vigenteEn($fecha)` antes de consultar `Turno`, devuelve `[]` si no hay asignación vigente. `AsignacionService::terminar()` cierra los `turno` asociados en la misma transacción. Test nuevo: `DisponibilidadTest::test_un_profesional_sin_asignacion_vigente_no_ofrece_slots_aunque_su_turno_siga_abierto`. Suite completa de Staffing+Scheduling verificada en verde (84 tests) antes y después del cambio.

**Motivación:** ver pregunta 52. Bug real en el motor de disponibilidad — el único componente que la spec marca como el que "cuesta clientes y reputación" si falla.

**Diseño (dos partes, seguridad en profundidad):**

1. **Fundamental — `DisponibilidadService::ventanasTurno()`:** antes de (o junto con) consultar `Turno`, verificar que exista una `Asignacion` vigente para ese `(profesional_id, local_id)`:
   ```php
   $asignacionVigente = Asignacion::where('local_id', $local->id)
       ->where('profesional_id', $profesional->id)
       ->vigente()
       ->exists();

   if (! $asignacionVigente) {
       return [];   // sin asignación vigente, no hay ventanas, sin importar los turnos
   }
   ```
   Implementa literalmente las dos condiciones del §5.1, filtro #2.

2. **Higiene de datos — `AsignacionService::terminar()`:** al terminar una asignación, cerrar también los `turno` de ese profesional en ese local (`vigente_hasta` = misma fecha), para que no queden turnos "vivos" sin asignación real detrás:
   ```php
   public function terminar(Asignacion $asignacion, ?string $hasta = null): Asignacion
   {
       $fechaFin = $hasta ?? now()->toDateString();

       DB::transaction(function () use ($asignacion, $fechaFin) {
           $asignacion->update(['hasta' => $fechaFin]);

           Turno::where('asignacion_id', $asignacion->id)
               ->whereNull('vigente_hasta')
               ->update(['vigente_hasta' => $fechaFin]);
       });

       return $asignacion;
   }
   ```

**Cubrir con test (crítico, mismo nivel de rigor que la suite de concurrencia):** terminar una asignación con turnos vigentes, verificar que el profesional deja de aparecer en `DisponibilidadService::slots()` para ese local a partir de esa fecha.

### [x] `recurso`: barbería sin dependencia obligatoria + recurso por defecto automático donde sí hace falta

**Implementado 2026-09-28**: los 7 servicios de `barberia` en `CatalogoServicioSeeder` cambiaron de `tipo_recurso` `'silla'` a `'ninguno'` (uñas/mascotas/estética sin cambio). `ServicioLocalService::crear()` gana `asegurarRecursoPorDefecto()`: si el `catalogo_servicio` activado requiere un tipo de recurso real (`codigo !== 'ninguno'`, mismo centinela que ya usaba `DisponibilidadService`) y el local no tiene ninguno activo de ese tipo, crea uno (`"{nombre del tipo} 1"`). Tests nuevos en `ServicioLocalTest.php`: crea el recurso por defecto en el primer servicio de un tipo, no lo duplica en un segundo servicio del mismo tipo, y no crea nada para servicios `'ninguno'`. **De paso**, encontrado y corregido drift real entre la especificación y el código de cambios ya implementados en una sesión anterior a esta: `context/fullpinta-especificacion.md` todavía documentaba `profesional.independiente` (ya eliminado), `genero`/`fecha_nacimiento` en `cliente_perfil` en vez de `usuario` (ya movidos), y le faltaban `solicitud_catalogo.solicitante_id` e `idempotencia.payload_hash` — los cuatro corregidos. Suite completa verificada en verde (504 tests).

**1. Seed del catálogo maestro (`CatalogoServicioSeeder`):** los servicios de la vertical `barberia` (Corte clásico, Corte fade, Corte + barba, Perfilado de barba, Tinte de barba, Cejas, Mascarilla negra...) cambian su `tipo_recurso` de `'silla'` a `'ninguno'`. Uñas y mascotas se quedan con su `tipo_recurso` real (`mesa_unas`, `tina`) sin ningún cambio.

**2. `ServicioLocalService::crear()`:** al activar un servicio que sí requiere un `tipo_recurso`, crear automáticamente un recurso por defecto si el local no tiene ninguno activo de ese tipo:

```php
if ($catalogoServicio->tipo_recurso_id !== null
    && ! Recurso::where('local_id', $local->id)
        ->where('tipo_recurso_id', $catalogoServicio->tipo_recurso_id)
        ->where('activo', true)
        ->exists()
) {
    Recurso::create([
        'local_id' => $local->id,
        'tipo_recurso_id' => $catalogoServicio->tipo_recurso_id,
        'nombre' => $catalogoServicio->tipoRecurso->nombre.' 1',
        'activo' => true,
    ]);
}
```

**Sin cambios:** `recurso`, `tipo_recurso`, `recursoLibre()`, el constraint `EXCLUDE cita_recurso_sin_traslape` — el mecanismo en sí queda intacto para donde de verdad se necesita.

### [x] ⚠️ PRIORIDAD ALTA — `CitaService::crear()` debe validar `excepcion` y `habilidad`, no solo confiar en lo que mostró `DisponibilidadService`

**Implementado 2026-09-28**: `verificarSinExcepcion()`, `verificarHabilidades()` y `verificarRecursoDelLocal()` agregados a `CitaService::crear()`; `cargarServicios()` ahora exige `local_id`; `agregarProducto()` valida que el producto sea del mismo local (ver también el bloque de `cita_producto` más abajo, mismo cambio). **Efecto colateral real encontrado y corregido**: las nuevas consultas añadieron suficiente latencia para exponer una fragilidad de `ConcurrenciaCitaTest` (servidores `php -S` de un solo worker poniéndose en cola) — se corrigió dándole un servidor dedicado por petición (`SERVIDORES = 20`), sin tocar la lógica de negocio; el `EXCLUDE` de Postgres nunca dejó de ganar exactamente una vez. Tests corregidos por falta de `Habilidad` en su fixture: `WalkInTest`, `ReagendarCitaTest`. Suite completa de Scheduling verificada en verde (44 tests) tras el arreglo.

**Motivación:** ver pregunta 59. Mismo patrón de bug que el de `asignacion`/`turno` (pregunta 52): el motor filtra correctamente para decidir qué mostrar, pero nadie vuelve a verificar al guardar.

**Diseño**, dentro de la misma transacción de `CitaService::crear()`, antes del `Cita::create(...)`:

```php
private function verificarSinExcepcion(Local $local, string $profesionalId, ?string $recursoId, CarbonImmutable $inicio, CarbonImmutable $fin): void
{
    $bloqueado = Excepcion::where(function ($q) use ($local, $profesionalId, $recursoId) {
        $q->where('local_id', $local->id)
          ->orWhere(fn ($q2) => $q2->where('profesional_id', $profesionalId)
              ->where(fn ($q3) => $q3->whereNull('local_id')->orWhere('local_id', $local->id)))
          ->orWhere('recurso_id', $recursoId);
    })
    ->where('fecha_inicio', '<', $fin)
    ->where('fecha_fin', '>', $inicio)
    ->exists();

    if ($bloqueado) {
        throw_validacion('Este horario no está disponible.', 'inicio');
    }
}

private function verificarHabilidades(string $profesionalId, Collection $servicios): void
{
    $conHabilidad = Habilidad::where('profesional_id', $profesionalId)
        ->whereIn('servicio_local_id', $servicios->pluck('id'))
        ->count();

    if ($conHabilidad !== $servicios->count()) {
        throw_validacion('El profesional no tiene la habilidad para alguno de los servicios pedidos.', 'servicios');
    }
}
```

**Nota honesta de diseño:** a diferencia de los tres `EXCLUDE` críticos (blindados a nivel de Postgres, inmunes a condiciones de carrera), esta es una verificación de aplicación — deja una ventana pequeña entre el chequeo y el `INSERT`. Aceptable porque `excepcion`/`habilidad` cambian con mucha menos frecuencia que las citas (no es el escenario de "20 clientes peleando por el mismo slot" que sí necesitaba blindaje real de base de datos).

**Cubrir con test:** intentar agendar dentro de una excepción activa → 422; intentar agendar un servicio sin la habilidad correspondiente → 422.

**Ampliación (pregunta 67): `servicio_local` y `recurso` deben pertenecer al `local` de la ruta.**

```php
private function cargarServicios(Local $local, array $ids): Collection
{
    $servicios = ServicioLocal::whereIn('id', $ids)
        ->where('local_id', $local->id)   // ← agregado, antes solo filtraba por id+activo
        ->where('activo', true)
        ->get();

    if ($servicios->count() !== count($ids)) {
        throw_validacion('Alguno de los servicios pedidos no existe, no está activo, o no pertenece a este local.', 'servicios');
    }

    return $servicios;
}
```

Y para `recurso_id` (si viene explícito en el request en vez de resuelto por `DisponibilidadService`): verificar `Recurso::find($recursoId)->local_id === $local->id` antes de aceptarlo, mismo criterio.

**Nota:** la corrección de `asignacion` vigente (arriba, en este mismo bloque) ya resuelve gratis el caso de `profesional_id` — verificar que exista una asignación vigente en `(profesional_id, local_id)` excluye automáticamente a un profesional que no trabaja en ese local. No hace falta un chequeo aparte para eso.

**Cubrir con test adicional:** intentar agendar con un `servicio_local_id`/`recurso_id` que pertenece a otro local → 422 en ambos casos.

**Ampliación (pregunta 69): `cita_producto` — mismo bug, quinta instancia.**

```php
// AgregarProductoRequest o CitaService::agregarProducto()
if ($producto->local_id !== $cita->local_id) {
    throw_validacion('El producto no pertenece al local de esta cita.', 'producto_id');
}
```

**Cubrir con test adicional:** intentar agregar un producto de otro local a una cita → 422.

### [x] Renombrar `cita_evento` → `cita_bitacora` + agregar FK a `actor_usuario_id`

**Motivación:** ver preguntas 70–71.

**Implementado 2026-09-28**: tabla renombrada en la migración base, `actor_usuario_id` con FK real a `usuario` (`nullOnDelete`). Modelo `CitaEvento` → `CitaBitacora`, factory renombrada, relación `Cita::eventos()` → `Cita::bitacora()` (sin llamadores externos, verificado). El trait `RegistraEventoCita` se deja con su nombre actual — describe la acción, no la tabla. Tests actualizados (`assertDatabaseHas`/`DB::table` con `cita_bitacora`). Suite de Scheduling+Identity verificada en verde (98 tests).

```sql
ALTER TABLE cita_evento RENAME TO cita_bitacora;
ALTER TABLE cita_bitacora
  ADD CONSTRAINT cita_bitacora_actor_usuario_id_foreign
  FOREIGN KEY (actor_usuario_id) REFERENCES usuario(id) ON DELETE SET NULL;
```

**Impacto en código:** modelo `CitaEvento.php` → `CitaBitacora.php`, trait `RegistraEventoCita` (revisar si también conviene renombrar), todas las clases de `Application/Transiciones/` que lo usan, `CitaItemResource`/`CitaResource` si lo exponen, tests que referencien el modelo/tabla vieja. Bajo riesgo — sin datos de producción todavía, mismo criterio que otras correcciones retroactivas de nombre en esta sesión.

### [x] ⚠️ PRIORIDAD ALTA — `disponibilidad_dia` debe recalcularse al crear/cancelar una cita

**Implementado 2026-09-28**: `InvalidarCacheDisponibilidad::handleCitaCreada()`/`handleCitaCancelada()` agregados, registrados en `SchedulingServiceProvider`. Test nuevo: `DisponibilidadDiaTest::test_crear_y_cancelar_una_cita_recalcula_disponibilidad_dia`.

**Motivación:** ver pregunta 73.

**Diseño:** agregar handlers en `InvalidarCacheDisponibilidad` (o un listener nuevo, si se prefiere no mezclar el origen Staffing/Scheduling) para:

```php
public function handleCitaCreada(CitaCreada $event): void
{
    $cita = Cita::find($event->citaId);
    if ($cita === null) {
        return;
    }
    ReconstruirDisponibilidadDia::dispatch($cita->local, CarbonImmutable::parse($cita->inicio))->onQueue('proyecciones');
}

public function handleCitaCancelada(CitaCancelada $event): void
{
    // mismo cuerpo — cubre cancelada_cliente/cancelada_local/no_show/expirada,
    // ya que CitaCancelada es un solo evento para los 4 casos (ver decisiones Fase 6)
}
```

`CitaCompletada` no necesita disparar esto — completar no cambia si el cupo estaba libre u ocupado, eso ya quedó reflejado al crearse la cita.

**Registrar en `SchedulingServiceProvider`** junto a los listeners ya existentes de `TurnoModificado`/`ExcepcionModificada`.

**Cubrir con test:** crear una cita que ocupe el último slot de un día → verificar que `disponibilidad_dia.slots_libres` baja; cancelarla → verificar que vuelve a subir.

### [x] `idempotencia`: detectar reuso de clave con distinto contenido

**Motivación:** ver pregunta 75.

**Implementado 2026-09-28**: agregada `payload_hash` en la migración base; el middleware la guarda al reclamar la clave y la compara en `reproducir()`. El `INSERT` que reclama la clave quedó envuelto en `DB::transaction()` — sin eso, el `23505` de clave duplicada dejaba la conexión de Postgres en estado abortado (`25P02`) para el `SELECT` de `reproducir()` que sigue en la misma transacción de prueba. Cubierto por `tests/Feature/Scheduling/IdempotenciaMiddlewareTest.php`.

```sql
ALTER TABLE idempotencia ADD COLUMN payload_hash varchar(64);
```

**Impacto en código** (`app/Http/Middleware/Idempotencia.php`):
- Al reclamar la clave (`INSERT`): guardar `hash('sha256', $request->getContent())` en `payload_hash`.
- En `reproducir()`: comparar el hash del request actual contra el guardado, junto a la validación ya existente de `usuario_id`/`endpoint`:
  ```php
  if ($fila->payload_hash !== hash('sha256', $request->getContent())) {
      return $this->error(422, 'idempotency_key_conflicto_payload',
          'Esa clave ya se usó con un contenido distinto.');
  }
  ```
- No se guarda el cuerpo completo del request — el hash cumple la garantía sin duplicar datos potencialmente sensibles (`nota_cliente`, etc.).

**Cubrir con test:** misma clave, mismo endpoint, mismo usuario, cuerpo distinto → 422 `idempotency_key_conflicto_payload`.

### [x] `reporte`: renombrar `tipo` → `objeto_type` + relación `MorphTo` nativa

**Implementado 2026-09-28**: columna renombrada en la migración base de Reviews (mismo `CHECK` de 4 valores, a diferencia de `imagen.objeto_type` que no lleva `CHECK` a propósito — el conjunto de qué se puede reportar sí está cerrado por decisión de producto). `Reporte::objeto(): MorphTo` nueva, cero configuración. `Relation::morphMap()` ganó `'resena' => Resena::class` y `'foto' => Imagen::class` (la tabla unificada, no las viejas `local_foto`/`profesional_foto`) — `'local'`/`'profesional'` ya estaban registrados desde el ítem de `imagen`. `CrearReporteRequest`/`ReporteService`/`ReporteResource`/`ReporteFactory` actualizados al nuevo nombre de campo (input y output). `docs/api-referencia.md` y la especificación actualizados. Suite completa verificada en verde (505 tests).

**Motivación:** ver preguntas 77–78. Mismo criterio de convención pura de Laravel ya decidido para `imagen`.

```sql
ALTER TABLE reporte RENAME COLUMN tipo TO objeto_type;
-- objeto_id ya se llama así, sin cambio
```

```php
// Reporte.php
use Illuminate\Database\Eloquent\Relations\MorphTo;

public function objeto(): MorphTo
{
    return $this->morphTo();   // cero configuración — objeto_type/objeto_id ya siguen la convención
}
```

**Impacto en código:** `CrearReporteRequest`/`ReporteService`/`ReporteResource` referencian el campo como `objeto_type` en vez de `tipo`. Se suma al `Relation::morphMap()` compartido con `imagen` (`'resena' => Resena::class`, `'foto' => Imagen::class` — este último ya apunta a la tabla `imagen` unificada, no a `local_foto`).

### [x] `cobro`: guardias de estado + método `marcarReembolsado()` faltante

**Motivación:** ver pregunta 81.

**Implementado 2026-09-28**: guardias agregadas a `marcarPagado()`/`marcarFallido()` y nuevo `marcarReembolsado()` en `CobroService`, endpoint `POST /cobros/{cobro}/marcar-reembolsado` (se usó ese nombre para seguir la convención `marcar-*` ya usada por `marcar-pagado`, en vez de `reembolsar`), documentado en `docs/api-referencia.md`. Cubierto por nuevos tests en `tests/Feature/Billing/CobroTest.php` (pagado→pagado, pendiente→reembolsado rechazado).

```php
public function marcarPagado(Cobro $cobro): Cobro
{
    if (! in_array($cobro->estado, ['pendiente', 'fallido'], true)) {
        throw_validacion("No se puede marcar pagado un cobro '{$cobro->estado}'.", 'estado');
    }

    $cobro->update([
        'estado' => 'pagado',
        'pagado_at' => now(),
        'comprobante_sri' => $this->sri->emitir($cobro),
    ]);

    return $cobro;
}

public function marcarFallido(Cobro $cobro): Cobro
{
    if ($cobro->estado !== 'pendiente') {
        throw_validacion("No se puede marcar fallido un cobro '{$cobro->estado}'.", 'estado');
    }

    $cobro->increment('intentos');
    $cobro->update(['estado' => 'fallido']);

    return $cobro;
}

public function marcarReembolsado(Cobro $cobro): Cobro
{
    if ($cobro->estado !== 'pagado') {
        throw_validacion("Solo se puede reembolsar un cobro 'pagado'.", 'estado');
    }

    $cobro->update(['estado' => 'reembolsado']);

    return $cobro;
}
```

**Impacto en código:** nuevo endpoint `POST /cobros/{cobro}/reembolsar` (hoy no existe ninguno para este estado). **Cubrir con test:** marcar pagado un cobro ya pagado → 422; marcar fallido un cobro ya pagado → 422; reembolsar un cobro pendiente → 422.

### [x] `suscripcion`: impedir dos suscripciones `'activa'` a la vez + recalcular `profesionales` en cada renovación

**Implementado 2026-09-28**: primera mitad (ver nota previa, ya en verde): índice único parcial `suscripcion_una_activa` + `SuscripcionService::activar()` transiciona cualquier `'activa'` previa a `'cancelada'`. Segunda mitad, ahora que existe `ActualizarVigenciaSuscripciones`: `renovarOEntrarEnGracia()` recalcula `profesionales` contando asignaciones vigentes reales en **cualquier** local del negocio (`distinct('profesional_id')` — un profesional en dos locales del mismo negocio no cuenta dos veces; uno cuya asignación ya terminó no cuenta), y recalcula `precio_mensual` con la fórmula del plan (`precio_base + precio_adicional × (profesionales − 1)`) antes de registrar el cobro del siguiente período. Test nuevo: `ActualizarVigenciaSuscripcionesTest::test_al_renovar_recalcula_profesionales_y_precio_segun_las_asignaciones_reales`. Suite completa verificada en verde (518 tests).

**Motivación:** ver preguntas 82–83.

```sql
CREATE UNIQUE INDEX suscripcion_una_activa ON suscripcion (negocio_id) WHERE estado = 'activa';
```

**Impacto en código:**
- `SuscripcionService::activar()`: si el negocio ya tiene una suscripción `'activa'`, transicionarla a `'cancelada'` (superseded) dentro de la misma transacción, antes de crear la nueva — en vez de dejar que el índice la rechace con un error confuso. Capturar SQLSTATE `23505` como respaldo y traducirlo con `throw_validacion()`, mismo patrón ya usado en `TurnoService`/`AsignacionService`.
- El job de vigencia (ya registrado en el bloque de `suscripcion`/§26) gana un paso más en cada renovación: recalcular `profesionales` contando las `asignacion` vigentes reales del negocio (a través de sus locales) y recalcular `precio_mensual` con la fórmula del plan (`precio_base` + `precio_adicional` × (profesionales − 1)) — no en tiempo real, solo al renovar el periodo.

**Cubrir con test:** activar una segunda suscripción para un negocio que ya tiene una activa → la vieja queda `cancelada`, solo una `activa` a la vez; renovar un periodo tras contratar más personal → `profesionales`/`precio_mensual` reflejan el conteo real.

### [x] ⚠️ PRIORIDAD ALTA — `local`: invalidar el caché del perfil público con evento de dominio, no asumir "intra-módulo"

**Implementado 2026-09-28**: `LocalService::invalidarPerfilPublico()` (público, nuevo). Evento `Catalog\Events\ServicioLocalModificado` (nuevo) disparado desde `ServicioLocalService::crear()/actualizar()/desactivar()`. Listener `Directory\Listeners\InvalidarCachePerfilPublico`, escucha `ServicioLocalModificado` + los ya existentes `ResenaCreada`/`ResenaRespondida` (Reviews), registrado en `DirectoryServiceProvider`. Las hermanas intra-módulo (`AmenidadService`, `HorarioLocalService`, `LocalFotoService`) llaman al método directo, sin evento, por ser mismo módulo. Tests nuevos en `PerfilPublicoLocalTest`: invalidación cruzada desde Catalog y desde Reviews. Suite de Directory+Catalog+Reviews verificada en verde (84 tests) antes de los tests nuevos.

**Motivación:** ver preguntas 85 y 87.

**Diseño:** nuevo evento `LocalPerfilModificado(string $localId)`, disparado desde:
- `ServicioLocalService::crear()`/`actualizar()`/`desactivar()` (Catalog)
- `ResenaService::crear()`/`responder()` (Reviews)
- Los servicios de amenidad/foto/horario de Directory que hoy tampoco lo invalidan

Un listener en Directory (`InvalidarCachePerfilPublico`, o método reusado de `LocalService`) escucha el evento y llama `Cache::forget("local:perfil-publico:{$localId}")` — mismo patrón ya establecido con `InvalidarCacheDisponibilidad` en Scheduling, en vez de la invalidación puntual desde cada método que resultó incompleta.

**Cubrir con test:** agregar un `servicio_local` nuevo a un local → el perfil público (ya cacheado antes del cambio) refleja el servicio nuevo sin esperar el TTL; misma prueba para una reseña nueva.

### [x] `cliente_local`: simplificar contadores + construir los endpoints de la ficha del cliente

**Implementado 2026-09-29**: columnas `primera_cita_at`/`ultima_cita_at`/`total_citas` eliminadas de `cliente_local` (editado en la migración base de Scheduling, sin datos de producción que migrar); índice `cita_cliente_local_estado ON cita (cliente_id, local_id, estado)` agregado. `CitaService::crear()` calcula `cliente_nuevo` directo contra `Cita` (existe una `completada` previa para ese cliente+local); `CompletarCita` ya no toca `cliente_local` en absoluto.

Nuevo `ClienteLocalService` (Application/Scheduling) con `ficha()` (nota + preferido + resumen de visitas en vivo, un solo `first()` sobre `cita`), `actualizar()` (crea la fila si no existe — valida que `profesional_preferido_id` tenga asignación **vigente en ese local**, con `Asignacion::vigente()`, no solo que el profesional exista en algún lado) y `bandejaMensual()` (`GROUP BY cliente_id` sobre `cita`, cacheado 5 min, mismo patrón que `BusquedaLocalService`). Controller/Requests/Resources nuevos (`ClienteLocalController`, `ActualizarClienteLocalRequest`, `BandejaClientesLocalRequest`, `ClienteLocalResource`, `ClienteLocalBandejaResource`), autorización vía `LocalPolicy::gestionarClientes()` (mismo permiso que `puedeVerAgendaCompleta`: propietario/admin/recepción). Rutas nuevas bajo `locales/{local}/clientes` en `Scheduling/routes.php`. `docs/api-referencia.md` y la especificación (§4.7, §4.12) actualizados en el mismo cambio.

**Motivación:** ver preguntas 61–62.

```sql
ALTER TABLE cliente_local
  DROP COLUMN primera_cita_at,
  DROP COLUMN ultima_cita_at,
  DROP COLUMN total_citas;
-- se quedan: usuario_id, local_id (PK), nota, profesional_preferido_id
```

**Impacto en código:**
- `CitaService::crear()`: `cliente_nuevo` se calcula directo contra `Cita` (`where('cliente_id')->where('local_id')->where('estado', 'completada')->exists()`), ya no depende de `ClienteLocal`.
- `CompletarCita.php`: se elimina el bloque que actualizaba `ClienteLocal` (`firstOrNew`, contadores) — ya no hace falta tocarla al completar una cita.
- **Índice nuevo**, para que la ficha individual sea rápida:
  ```sql
  CREATE INDEX cita_cliente_local_estado ON cita (cliente_id, local_id, estado);
  ```
- **Nuevos endpoints (hoy no existe ninguno, confirmado: sin Controller/Resource/Request para esta tabla):**
  - `GET /locales/{local}/clientes/{usuario}` — ficha individual del cliente: `nota`, `profesional_preferido`, y `total_citas`/`primera_cita_at`/`ultima_cita_at` calculados en vivo contra `cita` (usa el índice de arriba).
  - `PUT /locales/{local}/clientes/{usuario}` — staff actualiza `nota`/`profesional_preferido_id`.
  - `GET /locales/{local}/clientes?mes=YYYY-MM` — bandeja mensual: una sola consulta `GROUP BY cliente_id` sobre `cita` (filtrada por `local_id`+`estado='completada'`+rango del mes), con el total de visitas de cada cliente en esa ventana. Cacheado con TTL simple (ej. 5 min, `Cache::remember`), mismo patrón que `BusquedaLocalService` — sin invalidación por evento, porque es puro reporte.
  - Autorización: mismo criterio que ya gestiona el local (`propietario`/`admin`/`recepcion` vía `ContextoAcceso`).
- **Se evaluó y se descartó un trigger de Postgres** para mantener contadores sincronizados (pregunta 64) — sin efecto práctico aquí porque los contadores se eliminan, pero el criterio (eventos+Listener de PHP, no triggers de SQL, salvo lo que solo Postgres puede garantizar) queda documentado.

---

## Cambios ya implementados

_(vacío por ahora)_
