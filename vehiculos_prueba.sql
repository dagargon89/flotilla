-- SQL para insertar 6 vehículos de prueba en la tabla 'vehiculos'
-- Asegúrate de que la base de datos 'flotilla_interna' esté seleccionada (USE flotilla_interna;)

-- VEHÍCULOS PARA TRANSPORTE DE PERSONAS (Estatus inicial: disponible)
INSERT INTO
    vehiculos (
        marca,
        modelo,
        anio,
        placas,
        vin,
        tipo_combustible,
        kilometraje_actual,
        estatus,
        ubicacion_actual,
        observaciones
    )
VALUES (
        'Nissan',
        'Versa',
        2020,
        'ABC-123-D',
        '3N1BD7APXLL123456',
        'Gasolina',
        45200,
        'disponible',
        'Estacionamiento P1',
        'Ideal para traslados urbanos y personal.'
    ),
    (
        'Chevrolet',
        'Aveo',
        2021,
        'XYZ-987-A',
        '1G1BD5AT7M123457',
        'Gasolina',
        30500,
        'disponible',
        'Estacionamiento P2',
        'Compacto y económico, buen rendimiento.'
    ),
    (
        'Honda',
        'CR-V',
        2019,
        'DEF-456-E',
        '5J6RM4H74KLL123458',
        'Gasolina',
        78100,
        'disponible',
        'Estacionamiento P3',
        'Camioneta familiar, cómoda para viajes.'
    ),

-- VEHÍCULOS TIPO TRUCK (PARA CARGA PESADA) (Estatus inicial: disponible)
(
    'Ford',
    'F-150',
    2022,
    'GHI-789-F',
    '1FTFX1A61NKB123459',
    'Gasolina',
    15800,
    'disponible',
    'Patio de Carga Sur',
    'Pickup robusta, capacidad media de carga.'
),
(
    'Ram',
    '2500',
    2021,
    'JKL-012-B',
    '3C6JR6DT9MG123460',
    'Diésel',
    40000,
    'disponible',
    'Patio de Carga Norte',
    'Heavy-Duty, ideal para remolque y carga pesada.'
),
(
    'Isuzu',
    'NPR',
    2018,
    'MNO-345-C',
    'JF2LBR1A9G5123461',
    'Diésel',
    125000,
    'disponible',
    'Centro de Distribución',
    'Camión ligero de caja, alta capacidad para mercancía.'
);

-- Después de insertar, puedes verificar los datos con:
-- SELECT * FROM vehiculos;