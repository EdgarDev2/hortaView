<?php

namespace frontend\controllers;

//use frontend\models\CicloSiembra;
use common\components\DbHandler;
use frontend\models\Cultivo;
use frontend\models\RiegoManual;
use frontend\models\Valvula;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

/**
 * frontend/views/predicciones/
 * **/

class EstadisticasGeneralesController extends Controller
{
    public function behaviors()
    {
        return [
            // Filtro de control de acceso
            'access' => [
                'class' => AccessControl::class,
                'only' => ['index'], // Acciones a restringir
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'], // '@' solo usuarios autenticados tienen acceso
                    ],
                ],
            ],
            // Filtro de control de verbos HTTP
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'], // La acción 'delete' solo puede ser accedida mediante POST
                ],
            ],
        ];
    }
    public function actionIndex()
    {
        $resultados = DbHandler::obtenerCicloYFechas();
        $cicloSeleccionado = $resultados['cicloSeleccionado'];
        $fechaInicio = $resultados['fechaInicio'];
        $fechaFinal = $resultados['fechaFinal'];
        $descripcionCiclo = $resultados['descripcion'];

        // Obtener cultivos asociados al ciclo seleccionado
        $cultivos = Cultivo::find()
            ->select(['cultivoId', 'nombreCultivo'])
            ->where(['cicloId' => $cicloSeleccionado])
            ->orderBy(['nombreCultivo' => SORT_ASC])
            ->asArray()
            ->all();

        // Obtener los datos de riego manual y automático por cultivo
        $riegoManual = RiegoManual::find()
            ->select(['cultivoId', 'fechaEncendido', 'fechaApagado', 'volumen'])
            ->where(['cultivoId' => array_column($cultivos, 'cultivoId')])
            ->andWhere(['between', 'fechaEncendido', $fechaInicio, $fechaFinal])
            ->orderBy(['fechaEncendido' => SORT_ASC])
            ->asArray()
            ->all();

        $valvula = Valvula::find()
            ->select(['cultivoId', 'fechaencendido', 'volumen'])
            ->where(['cultivoId' => array_column($cultivos, 'cultivoId')])
            ->andWhere(['between', 'fechaEncendido', $fechaInicio, $fechaFinal])
            ->orderBy(['fechaEncendido' => SORT_ASC])
            ->asArray()
            ->all();

        // Combinar los volúmenes de riego manual y automático
        $datosRiego = array_merge($riegoManual, $valvula);

        // Inicializar un array para las métricas de cada cultivo
        $metricasPorCultivo = [];

        foreach ($cultivos as $cultivo) {
            // Filtrar los datos para el cultivo específico
            $datosCultivo = array_filter($datosRiego, function ($dato) use ($cultivo) {
                return $dato['cultivoId'] === $cultivo['cultivoId'];
            });

            // Calcular las métricas de riego para el cultivo
            $metricasPorCultivo[$cultivo['cultivoId']] = $this->calcularMetricasRiego($datosCultivo);
        }

        /*$datosGrafico = [
            'labels' => array_column($cultivos, 'nombreCultivo'),
            'promedios' => array_map(function ($cultivo) use ($metricasPorCultivo) {
                return $metricasPorCultivo[$cultivo['cultivoId']]['promedio'];
            }, $cultivos),
            'promedioMinimos' => array_map(function ($cultivo) use ($metricasPorCultivo) {
                return $metricasPorCultivo[$cultivo['cultivoId']]['promedioMinimos'];
            }, $cultivos),
            'promedioMaximos' => array_map(function ($cultivo) use ($metricasPorCultivo) {
                return $metricasPorCultivo[$cultivo['cultivoId']]['promedioMaximos'];
            }, $cultivos),
            'desviaciones' => array_map(function ($cultivo) use ($metricasPorCultivo) {
                return $metricasPorCultivo[$cultivo['cultivoId']]['desviacion'];
            }, $cultivos),
        ];*/
        $datosGrafico = [
            'labels' => array_column($cultivos, 'nombreCultivo'),
            'promedios' => array_map(function ($cultivo) use ($metricasPorCultivo) {
                return $metricasPorCultivo[$cultivo['cultivoId']]['promedio'];
            }, $cultivos),
            'maximos' => array_map(function ($cultivo) use ($metricasPorCultivo) {
                return $metricasPorCultivo[$cultivo['cultivoId']]['maximo'];
            }, $cultivos),
            'minimos' => array_map(function ($cultivo) use ($metricasPorCultivo) {
                return $metricasPorCultivo[$cultivo['cultivoId']]['minimo'];
            }, $cultivos),
            'desviaciones' => array_map(function ($cultivo) use ($metricasPorCultivo) {
                return $metricasPorCultivo[$cultivo['cultivoId']]['desviacion'];
            }, $cultivos),
            'descripcionCiclo' => $descripcionCiclo, // Agregar la descripción del ciclo
        ];



        return $this->render('index', [
            'cicloSeleccionado' => $cicloSeleccionado,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFinal,
            'cultivos' => $cultivos,
            'metricasPorCultivo' => $metricasPorCultivo,
            'datosGrafico' => $datosGrafico, // Pasar datos para el gráfico
        ]);
    }

    public function calcularMetricasRiego($datosCultivo)
    {
        $volumenes = array_column($datosCultivo, 'volumen'); // Extraer los valores de volumen

        return [
            'promedio' => count($volumenes) > 0 ? array_sum($volumenes) / count($volumenes) : 0, // Promedio
            'maximo' => count($volumenes) > 0 ? max($volumenes) : 0, // Valor máximo
            'minimo' => count($volumenes) > 0 ? min($volumenes) : 0, // Valor mínimo
            'desviacion' => $this->calcularDesviacion($volumenes), // Desviación estándar
        ];
    }

    private function calcularDesviacion($valores)
    {
        $n = count($valores);
        if ($n === 0) {
            return 0; // Si no hay datos, la desviación es 0
        }

        $media = array_sum($valores) / $n; // Calcular la media
        $sumaDesviaciones = array_reduce($valores, function ($carry, $valor) use ($media) {
            return $carry + pow($valor - $media, 2); // Suma de las diferencias al cuadrado
        }, 0);

        return sqrt($sumaDesviaciones / $n); // Raíz cuadrada de la media de las diferencias al cuadrado
    }


    /*private function calcularMetricasRiego($datos)
    {
        if (empty($datos)) {
            return [
                'promedio' => 0,
                'promedioMinimos' => 0,
                'promedioMaximos' => 0,
                'desviacion' => 0,
            ];
        }

        // Extraer volúmenes de agua
        $volumenes = array_column($datos, 'volumen');

        // Calcular promedio general
        $promedio = array_sum($volumenes) / count($volumenes);

        // Separar los volúmenes en mínimos y máximos
        $minimos = [];
        $maximos = [];
        foreach ($volumenes as $volumen) {
            if ($volumen < $promedio) {
                $minimos[] = $volumen;
            } else {
                $maximos[] = $volumen;
            }
        }

        // Calcular promedio de los mínimos y máximos
        $promedioMinimos = empty($minimos) ? 0 : array_sum($minimos) / count($minimos);
        $promedioMaximos = empty($maximos) ? 0 : array_sum($maximos) / count($maximos);

        // Calcular desviación estándar
        $desviacion = sqrt(array_sum(array_map(fn($v) => pow($v - $promedio, 2), $volumenes)) / count($volumenes));

        return [
            'promedio' => $promedio,
            'promedioMinimos' => $promedioMinimos,
            'promedioMaximos' => $promedioMaximos,
            'desviacion' => $desviacion,
        ];
    }*/
}
