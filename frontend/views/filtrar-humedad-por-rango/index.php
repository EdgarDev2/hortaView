<?php

use yii\helpers\Html;

$this->title = 'Filtrar humedad del suelo por rango';
$this->registerCssFile('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');
$this->registerJsFile('https://cdn.jsdelivr.net/npm/chart.js');
$this->registerJsFile('https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.1/dist/chartjs-plugin-zoom.min.js');
$this->registerCssFile('@web/css/chart_card.css');
// Clases comunes bootstrap
$btnClass = 'btn btn-outline-success btn-sm border-0 shadow-none';
$btnDownloadClass = 'btn btn-outline-primary btn-sm border-0 shadow-none';
$cardInputDate = 'input-group input-group-sm d-flex align-items-center gap-2 bg-light rounded px-2';
$inputDate = 'form-control placeholder-wave bg-transparent text-secondary';
$selectPlace = 'form-select placeholder-wave border-0 text-secondary bg-light rounded';
?>

<div class="filtrar-humedad-por-rango-index">
    <div class="card mt-0">
        <div class="card-header bg-primary text-white text-start">
            <h4 class="mb-0">Filtrar humedad del suelo por rango de fecha.</h4>
        </div>
        <!-- Botones de gráfico y filtros -->
        <div class="row p-2 border-bottom">
            <!-- Botones de gráfico y filtros -->
            <div class="col-md-12 d-flex flex-wrap align-items-center gap-2">
                <!-- Botones de tipo de gráfico -->
                <div class="btn-group" role="group" aria-label="Gráficos">
                    <button class="<?= $btnClass ?>" type="button" title="Gráfico de tipo Lineal" onclick="cambiarTipoGrafico('line')">
                        <i class="fas fa-chart-line"></i> Lineal
                    </button>
                    <button class="<?= $btnClass ?>" type="button" title="Gráfico de tipo Barra" onclick="cambiarTipoGrafico('bar')">
                        <i class="fas fa-chart-bar"></i> Barra
                    </button>
                    <button class="<?= $btnClass ?>" type="button" title="Gráfico de tipo Radar" onclick="cambiarTipoGrafico('radar')">
                        <i class="fas fa-chart-pie"></i> Radar
                    </button>
                    <button class="<?= $btnDownloadClass ?>" type="button" title="Descargar gráfico como imagen" onclick="descargarImagen('graficoCama', 'grafico_cama.png')">
                        <i class="fas fa-download"></i> Descargar
                    </button>
                </div>
                <!-- Filtros de fecha -->
                <div class="<?= $cardInputDate ?>" style="max-width: 250px;">
                    <label for="fechaInicio" class="form-label mb-0 text-secondary">Fecha Inicio:</label>
                    <input type="date" id="fechaInicio" class="<?= $inputDate ?>" style="width: 140px; border: none;" min="<?= $fechaInicio ?>" max="<?= $fechaFin ?>">
                </div>
                <div class=" <?= $cardInputDate ?>" style="max-width: 250px;">
                    <label for="fechaFin" class="form-label mb-0 text-secondary">Fecha Fin:</label>
                    <input type="date" id="fechaFin" class="<?= $inputDate ?>" style="width: 140px; border: none;" min="<?= $fechaInicio ?>" max="<?= $fechaFin ?>">
                </div>
                <!-- Selector de cama -->
                <div class="input-group input-group-sm" style="max-width: 162px;">
                    <select id="camaId" class="<?= $selectPlace ?>" title="Selecciona cama">
                        <option value="" disabled selected>Seleccionar cama</option>
                        <option value="1">Ka'anche' 1 cilantro automático</option>
                        <option value="2">Ka'anche' 2 rábano automático</option>
                        <option value="3">Ka'anche' 3 cilantro manual</option>
                        <option value="4">Ka'anche' 4 rábano manual</option>
                    </select>
                </div>
                <!-- Botón Filtrar -->
                <div>
                    <button id="btnFiltrar" class="btn btn-outline-primary btn-sm border-0 shadow-none">Filtrar datos</button>
                </div>
                <?= Html::a('Ver todos los registros', ['/todos-registros-camas/index'], ['class' => 'btn btn-outline-primary btn-sm border-0']) ?>
            </div>
        </div>
        <!-- Gráfico -->
        <div class="card-body bg-light">
            <div class="">
                <div class="chart-container" style="position: relative; height: 70vh; width: 100%;">
                    <canvas id="graficoCama" class="mt-0"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- Pasamos los datos de la sesión a JS -->
    <div id="data-container"
        data-ciclo="<?= $cicloSeleccionado ?>"
        data-fecha-inicio="<?= $fechaInicio ?>"
        data-fecha-fin="<?= $fechaFin ?>">
    </div>

</div>

<script>
    let chart; // Variable global para el gráfico
    let tipoGrafico = 'radar'; // Tipo de gráfico inicial

    // Función para inicializar el gráfico con zoom habilitado
    function inicializarGrafico(data) {
        const ctx = document.getElementById('graficoCama').getContext('2d');

        if (chart) {
            chart.destroy(); // Elimina el gráfico anterior si existe
        }

        chart = new Chart(ctx, {
            type: tipoGrafico,
            data: {
                labels: Array.from({
                    length: 24
                }, (_, i) => i + ':00 hrs'), // Horas
                datasets: [{
                        label: 'Mínimo',
                        data: data.minimos,
                        borderColor: '#36A2EB',
                        backgroundColor: 'rgba(54, 162, 235, 0.4)',
                        fill: tipoGrafico === 'line' ? false : true,
                        tension: 0.4, // Suaviza las líneas en gráficos de tipo 'line'
                        borderWidth: 2, // Opcional: ajusta el grosor de la línea
                    },
                    {
                        label: 'Promedio',
                        data: data.promedios,
                        borderColor: '#4BC0C0',
                        backgroundColor: 'rgba(75, 192, 192, 0.4)',
                        fill: tipoGrafico === 'line' ? false : true,
                        tension: 0.4, // Suaviza las líneas en gráficos de tipo 'line'
                        borderWidth: 2,
                    },
                    {
                        label: 'Máximo',
                        data: data.maximos,
                        borderColor: '#FF6384',
                        backgroundColor: 'rgba(255, 99, 132, 0.4)',
                        fill: tipoGrafico === 'line' ? false : true,
                        tension: 0.4, // Suaviza las líneas en gráficos de tipo 'line'
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                animations: {
                    tension: {
                        duration: 4000,
                        easing: 'linear',
                        from: 1,
                        to: 0,
                        loop: true
                    }
                },
                responsive: true,
                //maintainAspectRatio: false, // Permite ajustar el tamaño del gráfico
                maintainAspectRatio: false, // Mantiene la proporción de aspecto
                scales: tipoGrafico === 'radar' ? {
                    r: {
                        beginAtZero: true,
                        min: 0,
                        max: 100,
                        ticks: {
                            stepSize: 10,
                        },
                        angleLines: {
                            borderDash: [0, 0, 0, 55, 250]
                        },
                    },
                } : {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                        },
                        title: {
                            display: true,
                            text: 'Horas',
                        },
                    },
                    y: {
                        min: 0,
                        max: 100,
                        beginAtZero: true,
                        ticks: {
                            stepSize: 10,
                        },
                        title: {
                            display: true,
                            text: 'Humedad (%)',
                        },
                    },
                },
                plugins: {
                    zoom: {
                        zoom: {
                            wheel: {
                                enabled: true, // Habilitar zoom con rueda del ratón
                                speed: 0.1, // Velocidad del zoom
                            },
                            pinch: {
                                enabled: true, // Habilitar zoom con pinch
                                threshold: 2, // Número de dedos para activar el zoom
                            },
                            drag: {
                                enabled: true, // Habilitar desplazamiento
                            },
                        },
                    },
                },
            },
        });
    }


    // Función para realizar la solicitud AJAX
    function cargarDatos(fechaInicio, fechaFin, camaId) {
        console.log('Datos enviados:', {
            fechaInicio,
            fechaFin,
            camaId
        });
        $.ajax({
            type: 'POST',
            url: 'index.php?r=filtrar-humedad-por-rango/ajax',
            data: {
                fechaInicio,
                fechaFin,
                camaId
            },
            success: function(response) {
                if (response.success) {
                    console.log('promedio de mínimos', response.promedios_minimos);
                    console.log('promedio de máximos', response.promedios_maximos);
                    console.log('promedio de promedio', response.promedios);
                    inicializarGrafico(response);
                } else {
                    alert(response.message || 'Error al cargar los datos.');
                }
            },
        });
    }

    let dataContainer = document.getElementById('data-container');
    let fechaInicioo = dataContainer.dataset.fechaInicio; // Solo la fecha
    let fechaFinn = dataContainer.dataset.fechaFin; // Solo la fecha

    // Configuración para actualizar cada minuto
    function iniciarActualizacionAutomatica() {
        setInterval(() => {
            const fechaInicio = document.getElementById('fechaInicio').value || fechaInicioo;
            const fechaFin = document.getElementById('fechaFin').value || fechaFinn;
            const camaId = document.getElementById('camaId').value;
            cargarDatos(fechaInicio, fechaFin, camaId);
        }, 60000); // Cada 60,000 ms = 1 minuto
    }

    // Cambiar el tipo de gráfico
    function cambiarTipoGrafico(nuevoTipo) {
        tipoGrafico = nuevoTipo;
        const fechaInicio = document.getElementById('fechaInicio').value || fechaInicioo;
        const fechaFin = document.getElementById('fechaFin').value || fechaFinn;
        const camaId = document.getElementById('camaId').value;

        // Limpiar el canvas antes de redibujar
        const ctx = document.getElementById('graficoCama').getContext('2d');
        if (chart) {
            chart.destroy(); // Destruye el gráfico existente
        }

        // Cargar los datos
        cargarDatos(fechaInicio, fechaFin, camaId);
    }

    // Configurar el botón de filtrar
    document.getElementById('btnFiltrar').addEventListener('click', function() {
        const fechaInicio = document.getElementById('fechaInicio').value;
        const fechaFin = document.getElementById('fechaFin').value;
        const camaId = document.getElementById('camaId').value;

        if (!fechaInicio || !fechaFin || !camaId) {
            alert('Por favor, completa todos los filtros.');
            return;
        }
        cargarDatos(fechaInicio, fechaFin, camaId);
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('fechaInicio').value = fechaInicioo;
        document.getElementById('fechaFin').value = fechaFinn;

        const camaIdPredeterminada = '1';
        document.getElementById('camaId').value = camaIdPredeterminada;

        $(document).ready(function() {
            setTimeout(clickbutton, 10);

            function clickbutton() {
                $("#btnFiltrar").click();
            }
        });
        iniciarActualizacionAutomatica();
    });

    // Función para descargar el gráfico
    window.descargarImagen = function(canvasId, nombreArchivo) {
        let link = document.createElement('a');
        link.href = document.getElementById(canvasId).toDataURL();
        link.download = nombreArchivo;
        link.click();
    };
</script>