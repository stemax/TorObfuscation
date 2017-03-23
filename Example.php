<?php
interface A
{
    public function interMethod();
}

abstract class B implements A
{
    public function interMethod()
    {

    }
}
class Example extends B  {
    public static function getter($param, ...$arg)
    {
        var_dump($arg);
        $param = explode(',',$param);
        var_dump($param);
    }
}

Example::getter('1,2,3',[1,3,4,5],new stdClass());

class SoapProxyGenerator {

    /**
     * Alias for webservice code representation
     *
     * @var string
     */
    public $serviceAlias = 'Service';

    /**
     * Prefix for soap types
     * @var string
     */
    public $typePrefix = '';

    /**
     * Location where generated class will be saved
     * If empty result will be displayed on screen
     *
     * @var string
     */
    public $outputFile = '';

    /**
     * wheter or not try to find base types
     * *****EXPERIMENTAL*****
     * @var bool
     */
    public $tryFindBase = false;

    protected $types = array();

    protected $methods = array();

    protected $nativeTypes = array(
        'int',
        'integer',
        'string',
        'date',
        'datetime',
        'bool',
        'boolean',
        'float',
        'decimal',
    );

    /**
     * @var String
     */
    protected $wsdl = '';

    /**
     *
     * @var SimleXml representation of wsdl
     */
    protected $wsdlXml = null;

    /**
     * Class constructor
     *
     * @param string $wsdl - wsdl address
     * @param array $opts - SoapClient options see http://php.net/manual/en/soapclient.soapclient.php
     */
    public function __construct($wsdl, $opts) {
        $client = new SoapProxyClient($wsdl, $opts);
        $this->types = array_unique($client->__getTypes());
        $this->methods = array_unique($client->__getFunctions());
        $this->wsdl = $wsdl;
    }

    /**
     * Runs code generation over wsdl
     */
    public function generateCode() {

        if ($this->tryFindBase) {
            $this->wsdlXml = simplexml_load_file($this->wsdl);
        }

        $soapMethods = $this->parseMethods();
        $soapTypes = $this->parseTypes();

        $classStart = "<?php".PHP_EOL;
        $classStart .= $this->getComment();
        $classStart .= "class $this->serviceAlias extends SoapProxy {".PHP_EOL;

        $class = '';

        foreach($soapMethods as $method) {
            $class .= "\t/**".PHP_EOL;
            $class .= "\t* Genarated webservice method ".$method['method'].PHP_EOL;
            $class .= "\t*".PHP_EOL;

            $methodParams = array();
            $methodParamsVals = array();
            foreach($method['params'] as $param) {
                if (in_array($param['type'], $this->nativeTypes)) {
                    $methodParams[] = $param['name'];
                    $class .= "\t* @param ".$param['type'].' '.$param['name'].PHP_EOL;
                } else {
                    $methodParams[] = $this->typePrefix.$param['type'].' '.$param['name'];
                    $class .= "\t* @param ".$this->typePrefix.$param['type'].' '.$param['name'].PHP_EOL;
                }
                $methodParamsVals[] = $param['name'];

            }

            $class .= "\t* @return ".$this->typePrefix.$method['return'].PHP_EOL;
            $class .= "\t*/".PHP_EOL;

            $class .= "\tpublic function ".$method['method']."(";
            $class .= implode(' ', $methodParams);
            $class .= ") {".PHP_EOL;
            $class .= "\t\t".'return $this->soapClient->'.$method['method'].'('.implode(' ', $methodParamsVals).');'.PHP_EOL;
            $class .= "\t}".PHP_EOL.PHP_EOL;

        }

        $class .= PHP_EOL.PHP_EOL.'} //end generated proxy class'.PHP_EOL.PHP_EOL;

        $classMap = "\t".'protected $defaultTypeMap = array('.PHP_EOL;
        $classMapArray = array();
        $types = PHP_EOL.'/**********SOAP TYPES***********/'.PHP_EOL.PHP_EOL;
        foreach ($soapTypes as $type) {
            $classMapArray[] = "\t\t".'"'.$type['name'].'" => "'.$this->typePrefix.$type['name'].'"';
            $types .= $this->generateType($type);
        }

        $classMap .= implode(','.PHP_EOL, $classMapArray);
        $classMap .= PHP_EOL."\t);".PHP_EOL.PHP_EOL;

        $class = $classStart.$classMap.$class;

        if (!empty($this->outputFile)) {
            file_put_contents($this->outputFile, $class.$types);
        } else {
            highlight_string($class.$types);
        }
    }


    protected function parseTypes() {
        $soapTypes = array();

        foreach($this->types as $soapType) {
            $struct = explode(' ', str_replace(array("\n", "\t", " {", "{", "}", ";", '[', ']'), '', $soapType));
            $soapTypeName = $struct[0];
            $typeName = $struct[1];
            array_shift($struct);
            array_shift($struct);

            $fields = array();
            $index = 0;
            foreach ($struct as $k => $vars) {
                if ($k%2 == 0) { //variable type
                    $fields[$index]['type'] = $vars;
                } else { //variable name
                    $fields[$index]['name'] = $vars;
                    $index++;
                }
            }

            //try to find a base type class
            $base = $this->findBaseType($typeName);

            $soapTypes[] = array(
                'type' => $soapTypeName,
                'name' => $typeName,
                'fields' => $fields,
                'base' => $base
            );
        }

        return $soapTypes;
    }

    /**
     * Try to find if a given type has base class
     * @param string $typeName - name of wsdl type
     */
    protected function findBaseType($typeName) {
        if (!$this->tryFindBase) {
            return '';
        }
        $elem = $this->wsdlXml->xpath("//s:complexType[@name='".$typeName."']/s:complexContent/s:extension[@base]");

        $base = '';
        if (isset($elem[0])) {//found a base
            $base = (string)$elem[0]->attributes()->base;
            //replace namespacePart, with type prefix
            $baseParts = explode(':', $base);
            if (array_key_exists($baseParts[0], $this->wsdlXml->getDocNamespaces(true))) {
                array_shift($baseParts);
            }
            $base = $this->typePrefix.$baseParts[0];
        }
        return $base;
    }

    protected function parseMethods() {
        $soapMethods = array();
        foreach($this->methods as $method) {
            $struct = explode(' ', trim(str_replace(array("(", ")"), ' ', $method)));
            $returnType = $struct[0];
            $methodName = $struct[1];
            array_shift($struct);
            array_shift($struct);

            $params = array();
            $index = 0;
            foreach ($struct as $k=>$param) {
                if ($k%2 == 0) { //param type
                    $params[$index]['type'] = $param;
                } else { //param name
                    $params[$index]['name'] = $param;
                    $index++;
                }
            }

            $soapMethods[] = array (
                'return' => $returnType,
                'method' => $methodName,
                'params' => $params
            );
        }

        return $soapMethods;
    }

    protected function generateType($typeInfo) {

        $txt = '/**'.PHP_EOL;
        $txt .= '* Generated data proxy class for '.$typeInfo['type'].' '.$typeInfo['name'].PHP_EOL;
        $txt .= '*'.PHP_EOL;
        $txt .= '*/'.PHP_EOL;

        $extend = !empty($typeInfo['base']) ? (' extends '.$typeInfo['base']) : '';

        $txt .= 'class '.$this->typePrefix.$typeInfo['name'].$extend.' {'.PHP_EOL.PHP_EOL;
        foreach ($typeInfo['fields'] as $field) {
            $txt .= "\t/**".PHP_EOL;
            if (in_array($field['type'], $this->nativeTypes)) {
                $txt .= "\t* @var ".$field['type'].' $'.$field['name'].PHP_EOL;
            } else {
                $txt .= "\t* @var ".$this->typePrefix.$field['type'].' $'.$field['name'].PHP_EOL;
            }
            $txt .= "\t*/".PHP_EOL;
            $txt .= "\tpublic $".$field['name'].';'.PHP_EOL.PHP_EOL;
        }
        $txt .= '}'.PHP_EOL.PHP_EOL;

        return $txt;
    }

    private function getComment() {
        $txt = '/**'.PHP_EOL;
        $txt .- '*'.PHP_EOL;
        $txt .= '* Class to handle requests to '.$this->serviceAlias.' webservice.'.PHP_EOL;
        $txt .= '* This code was generated by using SoapProxy tool by przemek@otn.pl'.PHP_EOL;
        $txt .= '* Please do not modify it by hand.'.PHP_EOL;
        $txt .= '*'.PHP_EOL;
        $txt .= '*/'.PHP_EOL;
        return $txt;
    }


}