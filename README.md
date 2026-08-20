# Quiniela Mundialista

Aplicación web de quiniela deportiva: predicciones con puntos y ranking, más un
subsistema de apuestas con dinero real (cuotas, aprobación por tesorero, reparto por pozo).

Construida con arquitectura por capas (Core, Domain, Repository, Service, Http)
con separación de responsabilidades y transacciones seguras.

---

## Requisitos

| Componente | Detalle |
|---|---|
| Servidor | Apache (AppServ o equivalente) sobre Windows |
| PHP | 7.x con extensión `sqlsrv` (no PDO, no mysqli) |
| Base de datos | Microsoft SQL Server, accesible por red |
| Sin build | HTML + CSS + JS vanilla; no hay Composer, npm ni paso de compilación |

---

## Instalación

1. Copiar el proyecto a la carpeta que sirva Apache (p. ej. `C:\AppServ\www\Quiniela`).
2. Ejecutar `DBQUINIELA.sql` en SQL Server Management Studio para crear la base de datos.
3. Ejecutar en orden los scripts de `migrations/` (001 a 004) — no hay runner automático,
   se aplican a mano en SSMS.
4. Configurar credenciales (ver sección siguiente).
5. Abrir `http://localhost/Quiniela/login.php`.

## Configuración

Las credenciales (SQL Server, The Odds API, worldcup26.ir) **no viven en el código
versionado**. Copiar la plantilla y completarla:

```bash
cp config/local.example.php config/local.php
```

`config/local.php` no se versiona (ver `.gitignore`). Si no existe, la app falla
explícitamente al conectar a la base de datos en vez de usar un valor por defecto
incorrecto — es el comportamiento esperado hasta que se complete este paso.

## Competición activa

La competición activa se lee de la tabla `Competiciones` (columna `Activa`), cacheada
por request vía `Config::competicionActiva()`. Para cambiarla, actualizar esa columna
en la base de datos:

```sql
UPDATE Competiciones SET Activa = 0;
UPDATE Competiciones SET Activa = 1 WHERE Id = <id-de-la-competicion>;
```

## Sincronización automática

Una tarea del Programador de Windows ejecuta `bin/sync_once.php` cada 5 minutos, que hace
exactamente la misma llamada que el botón **Sincronizar** del panel de admin: consulta el
scoreboard de ESPN, actualiza partidos y recalcula puntos.

### Instalar

```bash
powershell -ExecutionPolicy Bypass -File bin/install_sync_task.ps1
```

Crea la tarea `Quiniela\SyncPartidos`. No requiere permisos de administrador ni guardar
contraseña. Para otro intervalo: `-IntervaloMinutos 15`.

### Desinstalar

```bash
powershell -ExecutionPolicy Bypass -File bin/uninstall_sync_task.ps1
```

### Operación

Ver el estado una vez (tarea + resumen del día + últimas pasadas):

```bash
powershell -ExecutionPolicy Bypass -File bin/sync_status.ps1
```

**Vigilancia continua** — se queda ejecutando indefinidamente. No redibuja: escribe una línea
de latido cada 20 s y va volcando las pasadas nuevas marcadas con `>>` según ocurren. Es el
modo para dejar una consola abierta (`Ctrl+C` para salir):

```bash
powershell -ExecutionPolicy Bypass -File bin/sync_status.ps1 -Vigilar
```

```
  17:55:55  tarea=Ready  ultimo=0  proxima en 03:02
  17:56:15  tarea=Ready  ultimo=0  proxima en 02:42
  >> [2026-07-28 17:58:58] INFO  sync ok procesados=40 nuevos=0 actualizados=1 evaluados=1 dur=1.3s
  17:59:15  tarea=Ready  ultimo=0  proxima en 04:43
```

Variantes: `-RefrescoSegundos 60` para un latido más espaciado, `-Panel` para una pantalla
fija que se redibuja entera, `-Seguir` para el log crudo sin latido.

Otras operaciones:

```bash
php bin/sync_once.php --dry-run    # comprueba BD y config, no escribe nada
php bin/sync_once.php              # fuerza una pasada ahora
schtasks /run /TN "Quiniela\SyncPartidos"
schtasks /query /TN "Quiniela\SyncPartidos" /V /FO LIST
```

Los logs están en `bin/logs/sync_YYYY-MM-DD.log` (una línea por pasada, se purgan a los
30 días). El campo **Último resultado** del Programador es el código de salida del script:

| Código | Significado |
|---|---|
| `0` | correcto |
| `1` | error recuperable (ESPN caída o bloqueada) — se reintenta en la siguiente pasada |
| `2` | ya había otra sincronización en curso — no es un error |
| `3` | error fatal (falta `config/local.php`, BD inalcanzable) |

La tarea y el botón del admin comparten un candado de archivo (`bin/logs/sync.lock`), así
que nunca corren a la vez; si se pulsa el botón durante una pasada, la web responde `409`.

**Los fallos `Connection was reset` contra ESPN son normales e intermitentes.** El endpoint
corta conexiones de forma irregular: durante la puesta en marcha alternó ventanas de fallo y
de éxito de pocos minutos cada una. Se comprobó que también falla desde PowerShell, en HTTP y
en HTTPS, así que **no es un problema de PHP, de curl ni del Programador de tareas**.

No hay que hacer nada. La pasada afectada registra `ERROR`, sale con código `1` y no toca la
BD; alguna de las siguientes (cada 5 minutos) entra sin problema. Como la sincronización es
idempotente y el marcador se lee entero en cada pasada, **perder pasadas no pierde datos**:
solo retrasa unos minutos la actualización.

Merece atención únicamente si el log encadena `ERROR` durante más de media hora seguida.

⚠️ **No lances pasadas manuales en ráfaga para "probar".** Durante la puesta en marcha se
hicieron ~25 peticiones a ESPN en 14 minutos (pasadas forzadas + sondas de diagnóstico) y el
host dejó de responder por completo, también a URLs de otras competiciones y también desde
PowerShell — el patrón típico del bloqueo anti-bot de Akamai, agravado porque `SyncService`
envía un User-Agent de navegador. La tarea en régimen normal hace **12 peticiones por hora**;
para diagnosticar, mira el log con `bin/sync_status.ps1` en vez de forzar pasadas.

### Suspensión y apagado

La tarea se registra con `WakeToRun`, que en equipos con suspensión clásica (S3) despierta
el PC para sincronizar. **En equipos con Modern Standby (S0 Low Power Idle) no basta** — ver
el apartado siguiente. Comprobar qué usa esta máquina:

```bash
powercfg /a
```

`WakeToRun` depende además de que el plan de energía permita temporizadores de reactivación.
El instalador lo comprueba y lo informa al terminar, por separado para corriente y batería:

```
  Despertar el equipo suspendido:
    Enchufado   : SI (temporizadores de reactivacion habilitados)
    Con bateria : NO - deshabilitados
      Habilitar: powercfg /setdcvalueindex SCHEME_CURRENT SUB_SLEEP RTCWAKE 1
```

Muchos portátiles traen los temporizadores desactivados con batería a propósito, para no
drenarla: despertar cada 5 minutos toda la noche cuesta autonomía real. Habilitarlo es una
decisión consciente, por eso el instalador no lo cambia solo.

Consultar el estado actual:

```bash
powercfg /query SCHEME_CURRENT SUB_SLEEP RTCWAKE
```

Con el equipo **apagado** no hay sincronización posible — ninguna tarea programada puede
encender un PC. Al arrancar, `StartWhenAvailable` ejecuta la pasada pendiente de inmediato.
En cualquier caso no se pierden datos: cada pasada lee el marcador completo, así que la
primera ejecución tras volver pone todo al día de golpe.

### Modern Standby: por qué `WakeToRun` no es suficiente

En una máquina con **Modern Standby** (`powercfg /a` muestra *Standby (S0 Low Power Idle)*),
la tarea se dispara durante la suspensión pero el sistema la **congela**: el *Desktop Activity
Moderator* suspende los procesos de usuario y corta la red. Medido en esta máquina:

```
9:50:56  107,129,100,200  <- la tarea arranca
9:57:05  322 (Warning)    <- se ignora el siguiente lanzamiento: la anterior sigue viva
9:57:06  201              <- termina 6 minutos despues
```

Una pasada que tarda 0,8 s tardó 6 minutos, y acabó en `ERROR Connection was reset` porque
no había red. Modern Standby tampoco genera los eventos clásicos `42`/`107` de suspensión,
así que `powercfg /lastwake` no informa de nada útil.

**Solución práctica: evitar que se duerma mientras esté enchufado.**

```bash
powercfg /change standby-timeout-ac 0
```

La pantalla se sigue apagando; solo se evita la suspensión con corriente. Revertir con
`powercfg /change standby-timeout-ac 20`.

El modo `-Vigilar` detecta estos huecos y los marca, en vez de dejar un salto de reloj
inexplicado:

```
  --- equipo suspendido ~44.3 min (09:06:40 -> 09:50:58): sin sincronizacion ---
```

### Otras limitaciones

- La tarea corre **con la sesión de Windows iniciada**. Para que corra también con la
  sesión cerrada hay que abrir `taskschd.msc` → `Quiniela\SyncPartidos` → Propiedades →
  *Ejecutar tanto si el usuario inició sesión como si no*, e introducir la contraseña de
  Windows.
- La URL de ESPN (`Config::ESPN_API_SCOREBOARD`) está fija a una competición concreta.
  Cambiar la competición activa en la BD **no** cambia a qué le pregunta la tarea.

## Backup de la base de datos

```bash
sqlcmd -S <servidor> -U sa -i bin/backup_db.sql
```

Genera un `.bak` comprimido con nombre y fecha en la carpeta de backups por defecto de
la instancia. SQL Server escribe el archivo en el disco del **servidor**, no en la
máquina donde se ejecuta el comando — para llevarlo a otra máquina hace falta un paso
de copia aparte.

## Pruebas de contrato (golden files)

No hay suite de tests automatizados. En su lugar, `tests/golden/` guarda snapshots de
las respuestas JSON reales de los endpoints de solo lectura, capturados con:

```bash
php bin/capture_contracts.php <baseUrl> <adminUser> <adminPass> [memberUser] [memberPass]
```

Las capturas guardan snapshots de respuestas reales para endpoints de solo lectura,
excluyendo endpoints que mutan datos o dinero real.
