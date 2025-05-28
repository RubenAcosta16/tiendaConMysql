// js/auth.js
document.addEventListener('DOMContentLoaded', function() {
    const isLoggedIn = localStorage.getItem('loggedIn');
    const userRole = localStorage.getItem('userRole');
    const currentPath = window.location.pathname;
    const baseDir = '/proyecto/'; // Asegúrate de que esta sea la ruta base de tu proyecto en el servidor XAMPP/WAMP

    // Rutas protegidas que requieren cualquier tipo de login
    const protectedPaths = [
        baseDir + 'dashboard_admin.php',
        baseDir + 'dashboard_cliente.php',
        baseDir + 'categorias.php',
        baseDir + 'productos.php',
        baseDir + 'proveedores.php',
        baseDir + 'productos_categorias.php',
        baseDir + 'datos_personas.php',
        baseDir + 'sucursales.php',
        baseDir + 'puesto.php',
        baseDir + 'empleados.php',
        baseDir + 'direcciones.php',
        baseDir + 'clientes.php',
        baseDir + 'reseñas.php',
        baseDir + 'ordenes_compra.php',
        baseDir + 'pagos.php'
    ];

    // Rutas exclusivas para admin
    const adminOnlyPaths = [
        baseDir + 'dashboard_admin.php',
        baseDir + 'categorias.php',
        baseDir + 'productos.php',
        baseDir + 'proveedores.php',
        baseDir + 'productos_categorias.php',
        baseDir + 'datos_personas.php',
        baseDir + 'sucursales.php',
        baseDir + 'puesto.php',
        baseDir + 'empleados.php',
        baseDir + 'direcciones.php',
        baseDir + 'clientes.php',
        baseDir + 'reseñas.php',
        baseDir + 'ordenes_compra.php',
        baseDir + 'pagos.php'
    ];

    // Rutas exclusivas para cliente
    const clientOnlyPaths = [
        baseDir + 'dashboard_cliente.php'
    ];


    // Si no estamos en la página de inicio (index.php) y no estamos logueados, redirigir al index
    if (currentPath !== baseDir + 'index.php') {
        if (isLoggedIn !== 'true') {
            alert('No has iniciado sesión. Serás redirigido al inicio.');
            window.location.href = baseDir + 'index.php';
            return; // Detener la ejecución
        }

        // Si estamos logueados, pero en una ruta protegida y el rol no coincide
        if (adminOnlyPaths.includes(currentPath) && userRole !== 'admin') {
            alert('Acceso denegado. Solo administradores pueden acceder a esta página.');
            // Redirigir a la página de cliente si es cliente, o al index si no se sabe el rol
            window.location.href = userRole === 'cliente' ? baseDir + 'dashboard_cliente.php' : baseDir + 'index.php';
            return;
        }

        if (clientOnlyPaths.includes(currentPath) && userRole !== 'cliente') {
            alert('Acceso denegado. Solo clientes pueden acceder a esta página.');
             // Redirigir a la página de admin si es admin, o al index si no se sabe el rol
            window.location.href = userRole === 'admin' ? baseDir + 'dashboard_admin.php' : baseDir + 'index.php';
            return;
        }
    }
});

console.log("nada");

// Función para cerrar sesión (se puede llamar desde un botón de logout)
function logout() {
    fetch('auth.php?logout=true')
        .then(response => response.json())
        .then(data => {
            // Limpiar localStorage
            localStorage.removeItem('loggedIn');
            localStorage.removeItem('userRole');
            localStorage.removeItem('userName');
            // Redirigir al index después de la respuesta del servidor
            window.location.href = 'index.php';
        })
        .catch(error => {
            console.error('Error al cerrar sesión:', error);
            alert('Hubo un problema al cerrar sesión.');
        });
}

document.addEventListener('DOMContentLoaded', function() {
    // ...tu código de protección de rutas...
});