<?php

namespace frontend\controllers;

use frontend\models\Cama1;
use frontend\models\Cama2;
use frontend\models\Cama3;
use frontend\models\Cama4;
//use frontend\models\CicloSiembra;
use common\components\DbHandler;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;
use Phpml\Regression\LeastSquares;
use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;

/**
 * frontend/views/predicciones/
 * **/

class PrediccionesController extends Controller
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

        return $this->render('index', [
            'cicloSeleccionado' => $cicloSeleccionado,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFinal,
        ]);
    }

    private function getModelByCamaId($camaId)
    {
        switch ($camaId) {
            case 1:
                return Cama1::class;
            case 2:
                return Cama2::class;
            case 3:
                return Cama3::class;
            case 4:
                return Cama4::class;
            default:
                return null;
        }
    }

    public function actionPredecir()
    {
        // Formato de respuesta JSON.
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Recepción de datos por POST.
        $camaId = Yii::$app->request->post('camaId');
        $fechaInicio = Yii::$app->request->post('fechaInicio');
        $fechaFin = Yii::$app->request->post('fechaFin');

        // Validación de parámetros.
        if (!$camaId || !$fechaInicio || !$fechaFin) {
            return ['success' => false, 'message' => 'Faltan datos requeridos.'];
        }

        // Formateo y validación de fechas.
        $fechaInicio = date('Y-m-d', strtotime($fechaInicio));
        $fechaFin = date('Y-m-d', strtotime($fechaFin));

        if ($fechaInicio > $fechaFin) {
            return ['success' => false, 'message' => 'La fecha de inicio no puede ser mayor que la fecha de fin.'];
        }

        // Obtención del modelo según el ID de la cama.
        $modelClass = $this->getModelByCamaId($camaId);

        // Consulta de promedios diarios
        $promedios = $modelClass::find()
            ->select(['fecha', 'AVG(humedad) AS promedio_humedad'])
            ->where(['between', 'fecha', $fechaInicio, $fechaFin])
            ->groupBy(['fecha'])
            ->orderBy(['fecha' => SORT_ASC])
            ->asArray()
            ->all();

        if (empty($promedios)) {
            return ['success' => false, 'message' => 'No se encontraron datos para el rango de fechas.'];
        }
        $datos = array_column($promedios, 'promedio_humedad');
        $predicciones = $this->predecirHumedadd($datos);

        //se ebvía la respuesta JSON.
        return [
            'success' => true,
            'datos_historicos' => $promedios,
            'predicciones' => $predicciones,
        ];
    }



    // Predecir humedad por predicción lineal simple.
    private function predecirHumedad($datos)
    {
        $samples = [];
        $targets = [];

        // Preparar datos para el modelo (X = días, Y = humedad promedio)
        foreach ($datos as $indice => $valor) {
            $samples[] = [$indice];
            $targets[] = $valor;
        }

        // Crear y entrenar el modelo
        $modelo = new LeastSquares();
        $modelo->train($samples, $targets);

        // Generar predicciones para los próximos 30 días
        $predicciones = [];
        $ultimoIndice = count($samples) - 1;

        for ($i = 1; $i <= 35; $i++) {
            $predicciones[] = $modelo->predict([$ultimoIndice + $i]);
        }

        return $predicciones;
    }
    private function predecirHumedadd($datos)
    {
        $samples = [];
        $targets = [];

        // Preparar los datos de entrenamiento
        foreach ($datos as $indice => $valor) {
            $samples[] = [$indice];
            $targets[] = $valor;
        }

        // Crear y entrenar el modelo usando SVR con Kernel RBF
        $modelo = new SVR(Kernel::LINEAR);
        $modelo->train($samples, $targets);

        // Predicciones para los próximos 35 días
        $predicciones = [];
        $ultimoIndice = count($samples) - 1;

        for ($i = 1; $i <= 50; $i++) {
            $predicciones[] = $modelo->predict([$ultimoIndice + $i]);
        }

        return $predicciones;
    }
}
