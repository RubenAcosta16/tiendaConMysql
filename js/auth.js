
document.addEventListener('DOMContentLoaded', function() {
    const isLoggedIn = localStorage.getItem('loggedIn');
    const userRole = localStorage.getItem('userRole');
    const currentPath = window.location.pathname;
    const baseDir = '/proyecto/'; 


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

    const clientOnlyPaths = [
        baseDir + 'dashboard_cliente.php'
    ];


    if (currentPath !== baseDir + 'index.php') {
        if (isLoggedIn !== 'true') {
            alert('No has iniciado sesión. Serás redirigido al inicio.');
            window.location.href = baseDir + 'index.php';
            return; 
        }

        if (adminOnlyPaths.includes(currentPath) && userRole !== 'admin') {
            alert('Acceso denegado. Solo administradores pueden acceder a esta página.');
            window.location.href = userRole === 'cliente' ? baseDir + 'dashboard_cliente.php' : baseDir + 'index.php';
            return;
        }

        if (clientOnlyPaths.includes(currentPath) && userRole !== 'cliente') {
            alert('Acceso denegado. Solo clientes pueden acceder a esta página.');
            window.location.href = userRole === 'admin' ? baseDir + 'dashboard_admin.php' : baseDir + 'index.php';
            return;
        }
    }
});

console.log("nada");

function logout() {
    fetch('auth.php?logout=true')
        .then(response => response.json())
        .then(data => {
            localStorage.removeItem('loggedIn');
            localStorage.removeItem('userRole');
            localStorage.removeItem('userName');
            window.location.href = 'index.php';
        })
        .catch(error => {
            console.error('Error al cerrar sesión:', error);
            alert('Hubo un problema al cerrar sesión.');
        });
}

document.addEventListener('DOMContentLoaded', function() {
    // console.log{"protege"}
});