<?php

class Twocheckout_Util
{

    static function return_resp($contents, $format) {
        switch ($format) {
            case "array":
                $arrayObject = self::objectToArray($contents);
                return $arrayObject;
                break;
            case "force_json":
                $arrayObject = self::objectToJson($contents);
                return $arrayObject;
                break;
            default:
                $jsonData = json_encode($contents);
                return json_decode($jsonData);
        }
    }

    public function objectToArray($object)
    {
        $object = json_decode($object, true);
        $array=array();
        foreach($object as $member=>$data)
        {
            $array[$member]=$data;
        }
        return $array;
    }

    public function objectToJson($object)
    {
        return json_encode($object);
    }

    static function get_recurring_lineitems($saleDetail) {
        $i = 0;
        $invoiceData = array();

        while (isset($saleDetail['sale']['invoices'][$i])) {
            $invoiceData[$i] = $saleDetail['sale']['invoices'][$i];
            $i++;
        }

        $invoice = max($invoiceData);
        $i = 0;
        $lineitemData = array();

        while (isset($invoice['lineitems'][$i])) {
            if ($invoice['lineitems'][$i]['billing']['recurring_status'] == "active") {
                $lineitemData[$i] = $invoice['lineitems'][$i]['billing']['lineitem_id'];
            }
            $i++;
        };

        return $lineitemData;

    }

}