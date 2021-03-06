<?php
/**
 * Acoes do modulo "Call".
 *
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author  Adilson Leffa Magnus.
 * @copyright   Todos os direitos reservados.
 * ###################################
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 17/08/2012
 */

class CallSummaryPerUserController extends Controller
{
    public $config;
    public $attributeOrder = 't.id_user DESC';
    public $extraValues    = array('idUser' => 'username');

    public $fieldsFkReport = array(
        'id_user' => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => 'username',
        ),
    );

    public function init()
    {

        $this->instanceModel = new CallSummaryPerUser;
        $this->abstractModel = CallSummaryPerUser::model();
        $this->titleReport   = Yii::t('yii', 'Calls summary per User');
        parent::init();

    }

    public function recordsExtraSum($records = array())
    {
        foreach ($records as $key => $value) {
            $records[0]->sumsessiontime += $value['sessiontime'] / 60;
            $records[0]->sumsessionbill += $value['sessionbill'];
            $records[0]->sumbuycost += $value['buycost'];
            $records[0]->sumaloc_all_calls += $value['sessiontime'] / $value['nbcall'];
            $records[0]->sumnbcall += $value['nbcall'];
            $records[0]->sumnbcallfail += $value['nbcall_fail'];
        }

        $this->nameSum = 'sum';

        return $records;
    }

    public function getAttributesModels($models, $itemsExtras = array())
    {
        $attributes = false;
        foreach ($models as $key => $item) {
            $attributes[$key]                   = $item->attributes;
            $attributes[$key]['nbcall']         = $item->nbcall;
            $attributes[$key]['lucro']          = $item->sessionbill - $item->buycost;
            $attributes[$key]['sessiontime']    = $item->sessiontime / 60;
            $attributes[$key]['aloc_all_calls'] = $item->aloc_all_calls;
            $attributes[$key]['sumsessionbill'] = $item->sumsessionbill;
            $attributes[$key]['sumbuycost']     = $item->sumbuycost;
            $attributes[$key]['sumlucro']       = $item->sumsessionbill - $item->sumbuycost;
            $attributes[$key]['sumnbcall']      = $item->sumnbcall;
            $attributes[$key]['sumsessiontime'] = $item->sumsessiontime;
            $attributes[$key]['sumnbcallfail']  = $item->sumnbcallfail;
            if (isset(Yii::app()->session['idClient']) && Yii::app()->session['idClient']) {
                foreach ($this->fieldsInvisibleClient as $field) {
                    unset($attributes[$key][$field]);
                }
            }

            if (isset(Yii::app()->session['idAgent']) && Yii::app()->session['idAgent']) {
                foreach ($this->fieldsInvisibleAgent as $field) {
                    unset($attributes[$key][$field]);
                }
            }

            foreach ($itemsExtras as $relation => $fields) {
                $arrFields = explode(',', $fields);
                foreach ($arrFields as $field) {
                    $attributes[$key][$relation . $field] = $item->$relation->$field;
                    if (Yii::app()->session['idClient']) {
                        foreach ($this->fieldsInvisibleClient as $field) {
                            unset($attributes[$key][$field]);
                        }
                    }

                    if (Yii::app()->session['idAgent']) {
                        foreach ($this->fieldsInvisibleAgent as $field) {
                            unset($attributes[$key][$field]);
                        }
                    }
                }
            }
        }

        return $attributes;
    }

    public function actionExportCsvCalls()
    {

        if (!Yii::app()->session['isAdmin']) {
            exit;
        }

        $this->setfilter($_GET);

        $username = explode(' - ', $_GET['id'])[0];
        $this->filter .= ' AND username = :keyusername';

        $this->paramsFilter[':keyusername'] = $username;

        $this->magnusFilesDirectory = '/var/www/tmpmagnus/';
        $nameFileCsv                = $this->nameFileReport . time();
        $pathCsv                    = $this->magnusFilesDirectory . $nameFileCsv . '.csv';

        $this->convertRelationFilter();

        $columns    = 'u.username,CONCAT(firstname, " ",lastname),starttime,calledstation,sessiontime,real_sessiontime,buycost,sessionbill,trunkcode ';
        $this->join = 'JOIN pkg_user u ON t.id_user = u.id ';
        $this->join .= 'JOIN pkg_trunk r ON t.id_trunk = r.id ';
        $sql = "SELECT " . $columns . "  INTO OUTFILE '" . $this->magnusFilesDirectory . $nameFileCsv . ".csv' FIELDS TERMINATED BY '\;' LINES TERMINATED BY '\n'
                FROM pkg_cdr t $this->join WHERE $this->filter";

        $command = Yii::app()->db->createCommand($sql);
        if (count($this->paramsFilter)) {
            foreach ($this->paramsFilter as $key => $value) {
                $command->bindValue($key, $value, PDO::PARAM_STR);
            }

        }

        $command->execute();
        header('Content-type: application/csv');
        header('Content-Disposition: inline; filename="' . $this->modelName . '_' . time() . '.csv"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        ob_clean();
        flush();
        if (readfile($pathCsv)) {
            unlink($pathCsv);
        }

    }

}
