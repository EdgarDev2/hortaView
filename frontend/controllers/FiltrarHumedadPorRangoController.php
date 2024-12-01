<?php

namespace frontend\controllers;

use frontend\models\Cama1;
use frontend\models\Cama2;
use frontend\models\Cama3;
use frontend\models\Cama4;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

/**
 * frontend/views/predicciones/
 * **/

class FiltrarHumedadPorRangoController extends Controller
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
    protected function obtenerDatosHumedad($modelClass, $fechaInicio, $fechaFin)
    {
        $data = $modelClass::find()
            ->select([
                'HOUR(hora) as hora', // Extrae la hora
                'AVG(humedad) as promedio_humedad', // Calcula el promedio general
                'MAX(humedad) as max_humedad', // Máximo
                'MIN(humedad) as min_humedad', // Mínimo
            ])
            ->where(['between', 'fecha', $fechaInicio, $fechaFin]) // Filtra por rango de fechas
            ->groupBy(['HOUR(hora)']) // Agrupa por hora
            ->orderBy(['hora' => SORT_ASC]) // Ordena por hora
            ->asArray()
            ->all();

        $resultados = [
            'promedios' => array_fill(0, 24, null),
            'maximos' => array_fill(0, 24, null),
            'minimos' => array_fill(0, 24, null),
        ];

        foreach ($data as $entry) {
            $hora = (int)$entry['hora']; // Hora extraída
            $resultados['promedios'][$hora] = (float)$entry['promedio_humedad'];
            $resultados['maximos'][$hora] = (float)$entry['max_humedad'];
            $resultados['minimos'][$hora] = (float)$entry['min_humedad'];
        }

        return $resultados;
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionAjax()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $fechaInicio = Yii::$app->request->post('fechaInicio');
        $fechaFin = Yii::$app->request->post('fechaFin');
        $camaId = Yii::$app->request->post('camaId');

        $modelClass = match ($camaId) {
            '1' => Cama1::class,
            '2' => Cama2::class,
            '3' => Cama3::class,
            '4' => Cama4::class,
            default => null,
        };

        if ($modelClass === null || !strtotime($fechaInicio) || !strtotime($fechaFin)) {
            return ['success' => false, 'message' => 'Datos inválidos'];
        }

        try {
            $resultados = $this->obtenerDatosHumedad($modelClass, $fechaInicio, $fechaFin);
            return [
                'success' => true,
                'promedios' => $resultados['promedios'],
                'maximos' => $resultados['maximos'],
                'minimos' => $resultados['minimos'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al procesar la solicitud'];
        }
    }
}
