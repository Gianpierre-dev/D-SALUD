# Sistema D'Salud — Gestión de Ventas e Inventario

Sistema web para la **botica D'Salud S.A.C.** que automatiza la gestión de ventas y el control de inventario, con trazabilidad por lote y fecha de vencimiento (regla **FEFO**), boletas internas con numeración correlativa, alertas de stock y vencimiento, reportes y control de acceso por roles.

---

## Tecnologías

| Capa | Tecnología |
| ---- | ---------- |
| Backend | Laravel 11 (PHP 8.3) |
| Puente front-back | Inertia.js |
| Frontend | React 19 + Tailwind CSS |
| Base de datos | MySQL 8 + Eloquent ORM |
| Autenticación | Laravel Breeze (sesión) + RBAC (spatie/laravel-permission) |
| Reportes | Laravel Excel (maatwebsite/excel) |

Arquitectura **monolítica** (una sola aplicación): el frontend React se renderiza mediante Inertia, sin una API REST separada.

---

## Requisitos previos

Antes de instalar, tu equipo debe tener:

- **PHP 8.3** o superior (con las extensiones `zip`, `gd`, `intl`, `mbstring`)
- **Composer 2**
- **Node.js 20+** y **npm**
- **MySQL 8** (recomendado: usar **[Laragon](https://laragon.org/)**, que incluye PHP y MySQL)

---

## Instalación (solo la primera vez)

```bash
# 1. Instalar dependencias del backend
composer install

# 2. Instalar dependencias del frontend
npm install

# 3. Crear el archivo de configuración
copy .env.example .env      # En Windows
# cp .env.example .env       # En Mac/Linux

# 4. Generar la clave de la aplicación
php artisan key:generate

# 5. Crear la base de datos "dsalud" en MySQL
#    (con Laragon: clic derecho > MySQL > o desde HeidiSQL)

# 6. Ejecutar las migraciones y datos iniciales
php artisan migrate --seed
```

> El archivo `.env` ya viene configurado para conectarse a MySQL en `localhost:3306`,
> base de datos `dsalud`, usuario `root` sin contraseña (configuración estándar de Laragon).
> Si tu MySQL usa otra contraseña, edítala en `.env` (variable `DB_PASSWORD`).

---

## Cómo iniciar el sistema

### Opción A — Fácil (recomendada para usuarios no técnicos)

1. Asegúrate de tener **Laragon encendido** (para la base de datos).
2. Haz **doble clic en el archivo `iniciar.bat`**.
3. El sistema se abrirá solo en tu navegador en **http://localhost:8000**.
4. Para apagarlo, cierra las dos ventanas negras que se abrieron.

### Opción B — Manual (dos terminales)

```bash
# Terminal 1 — servidor de la aplicación
php artisan serve

# Terminal 2 — recursos visuales (Vite)
npm run dev
```

Luego entra a **http://localhost:8000**.

---

## Puertos

| Puerto | Servicio | ¿Es el de acceso? |
| ------ | -------- | ----------------- |
| **8000** | Aplicación (Laravel) | ✅ **Sí — entrá acá: http://localhost:8000** |
| 5173 | Vite (recursos JS/CSS y recarga en caliente) | ❌ No — trabaja por detrás |

> En este sistema **se accede por un solo puerto (8000)**. Vite (5173) solo entrega
> los recursos visuales durante el desarrollo; no se navega directamente a ese puerto.

---

## Usuarios de acceso (datos iniciales)

| Rol | Correo | Contraseña |
| --- | ------ | ---------- |
| Administrador | `admin@dsalud.com` | `password` |
| Vendedor | `vendedor@dsalud.com` | `password` |

- **Administrador**: acceso total (catálogos, ventas, reportes, usuarios, roles, auditoría, configuración).
- **Vendedor**: registro y consulta de ventas, y consulta del catálogo.

> Cambia estas contraseñas antes de usar el sistema en producción.

---

## Módulos

- **Autenticación y Roles** — acceso seguro y permisos granulares por módulo.
- **Categorías, Productos, Proveedores** — catálogos maestros.
- **Lotes / Inventario** — control por lote con fecha de vencimiento (FEFO) y alertas.
- **Ventas (Punto de Venta)** — registro con descuento automático de stock y boleta correlativa.
- **Historial de Ventas** — consulta, anulación (con reposición de stock) y reimpresión de boletas.
- **Dashboard** — indicadores del día y alertas de stock bajo y productos por vencer.
- **Reportes** — exportación a Excel (ventas, productos más vendidos, por vencer, stock bajo, auditoría).
- **Auditoría** — registro de operaciones críticas.
- **Configuración** — datos de la empresa.

---

## Configuración personalizable (`.env`)

| Variable | Descripción | Valor por defecto |
| -------- | ----------- | ----------------- |
| `DSALUD_BOLETA_SERIE` | Serie de las boletas internas | `B001` |
| `DSALUD_DIAS_ALERTA_VENCIMIENTO` | Días de anticipación para alertar productos por vencer | `30` |
| `DSALUD_PAGINACION_POR_PAGINA` | Registros por página en los listados | `15` |

---

## Despliegue en producción

Para entregar el sistema a un usuario final (sin que instale nada en su equipo),
se recomienda desplegarlo en **[Railway](https://railway.app/)**: el usuario accede
mediante una URL desde cualquier navegador, sin necesidad de instalar PHP, MySQL ni Node.

---

## Comandos útiles

```bash
php artisan migrate:fresh --seed   # Reiniciar la base de datos con datos iniciales
php artisan route:list             # Ver todas las rutas del sistema
php artisan db:seed                # Volver a cargar los datos iniciales
```
