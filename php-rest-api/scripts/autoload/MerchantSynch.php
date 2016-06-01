<?php
use Aws\Common\Aws;

class MerchantSynch
{
    /**
     * Function to trigger OCR synchronizations
     *
     */
    public static function updateOCR ($merchantIDs)
    {
        if (!isset($merchantIDs)) return;

        $idList = (!is_array($merchantIDs)) ? $merchantIDs : implode(',', $merchantIDs);

        $ocrIPs = self::getOcrIps();

        foreach ($ocrIPs as $ip) {
            $command = 'php ../scripts/merchant_sync.php "' . $ip . '" "' . $idList . '"';
            if ($_ENV['STAGE'] != STAGE_DEV) {
                $command .= ' > /dev/null 2>/dev/null &';
            }

            shell_exec($command);
        }
    }

    /**
     * Function to get private ip of OCR in current VPC and region
     *
     */
    public static function getOcrIps ()
    {
        $ec2 = Aws::factory(array(
            'key'    => Config::get('aws::config.key'),
            'secret' => Config::get('aws::config.secret'),
            'region' => $_ENV['AWS_VPC_REGION']
        ))->get('ec2');

        $res = $ec2->describeInstances(array(
            'Filters' => array(
                array('Name' => 'subnet-id', 'Values' => array($_ENV['AWS_OCR_SUBNET_ID'])),
                array('Name' => 'instance-state-name', 'Values' => array('running')),
            )
        ));

        $ipList = array();
        foreach ($res['Reservations'] as $row) {
            $ipList[] = $row['Instances'][0]['PrivateIpAddress'];
        }

        return $ipList;
    }

    /**
     * Function to generate data which will be sent to OCR for synchronization
     *
     */
    public static function buildDataPackage ($merchantIDs, $ocrIP = '')
    {
        $data = array();

        //Fields that matched with database columns
        $fields = array('PaperReceipt', 'PaperInvoice', 'EmailReceipt', 'EmailInvoice', 'DigitalReceipt', 'DigitalInvoice');
        $seed = array();
        foreach ($fields as $f) {
            $seed[$f] = array(
                'FindName' => array(),
                'Bot' => ''
            );
        }

        if ($ocrIP) {
            $fallbacks   = explode(",", retrieveFallback($ocrIP));
            $merchantIDs = array_unique(array_merge($merchantIDs, $fallbacks));
        }

        //Run the building
        foreach ($merchantIDs as $id) {
            $mc = array_merge(array(), $seed);  //Clone template data
            $mc['MerchantID'] = intval($id);    //Add merchant ID

            $query = DB::table('MerchantAlgos')->where('MerchantID', $id)->first();

            if ($query) {
                $query = (array) $query;
            }

            $mc = static::extractAlgos($query, $fields, $mc);

            $data[] = $mc;
        }

        return $data;
    }

    /**
     * Function to explode find name from string in database into array list
     *
     */
    public static function explodeFindName ($string) {
        return explode('~|', $string);
    }

    /**
     * Extract algos from queried row
     *
     */
    public static function extractAlgos ($row, $fields, $mc) {
        foreach ($fields as $f) {
            if (isset($row[$f . 'Bot'])) {
                $mc[$f]['FindName'] = static::explodeFindName($row[$f . 'FindName']);
                $mc[$f]['Bot'] = $row[$f . 'Bot'];
            }
        }

        return $mc;
    }

    /**
     * Function to store fall-back synchronizations of merchant
     *
     */
    public static function storeFallback ($ocrIP, $merchantIDs)
    {
        if (is_array($merchantIDs)) {
            $merchantIDs = implode(",", $merchantIDs);
        }

        DB::table('MerchantOcrSync')
            ->insert(array(
                'OcrIP'       => $ocrIP,
                'MerchantIDs' => $merchantIDs,
                'Created'     => time()
            ));
    }

    /**
     * Function to get fall-back synchronizations of merchant
     *
     */
    function retrieveFallback ($ocrIP)
    {
        return DB::table('MerchantOcrSync')->where('OcrIP', $ocrIP)->first();
    }

    /**
     * Function to get fall-back synchronizations of merchant
     *
     */
    public static function deleteFallback ($ocrIP)
    {
        return DB::table('MerchantOcrSync')->where('OcrIP', $ocrIP)->delete();
    }

}
