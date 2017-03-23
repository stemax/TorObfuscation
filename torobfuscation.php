<?php
namespace TorObfuscation;

class Obfuscation
{
    public static $replace_functions, $functions, $variables, $replace_variables;

    public static function getVariables($code = '')
    {
        $pattern = '/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/';
        preg_match_all($pattern, $code, $matches);
        $result = array_unique($matches[0]);
        return $result;
    }

    public static function getFunctions($code = '')
    {
        $pattern = '/on ([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/';
        preg_match_all($pattern, $code, $matches);
        $result = array_unique($matches[1]);
        return $result;
    }


    public static function obfuscate($code = '')
    {
        $functions = Obfuscation::getFunctions($code);
        $replace_functions = [];
        if (sizeof($functions)) {
            foreach ($functions as $function) {
                $length = 15;
                $replace_fun_name = self::generateRandomVariableName($length);
                while (in_array($replace_fun_name, $replace_functions)) {
                    $length++;
                    $replace_fun_name = self::generateRandomVariableName($length);
                }
                $replace_functions[] = $replace_fun_name;
                self::$replace_functions[] = $replace_fun_name;
                self::$functions[] = $function;
            }
            $code = str_replace($functions, $replace_functions, $code);
        }

        $variables = Obfuscation::getVariables($code);
        $replace_variables = [];
        if (sizeof($variables)) {
            foreach ($variables as $variable) {
                $length = 10;
                $replace_var_name = self::generateRandomVariableName($length);
                while (in_array($replace_var_name, $replace_variables)) {
                    $length++;
                    $replace_var_name = self::generateRandomVariableName($length);
                }
                $replace_variables[] = '$' . $replace_var_name;
                self::$replace_variables[] = $replace_var_name;
                self::$variables[] = $variable;
            }
            return str_replace($variables, $replace_variables, $code);
        }
    }

    public static function generateRandomVariableName($length = 1)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            if ($i > 1) {
                $characters = '0123456789_';
                $charactersLength = strlen($characters);
            }
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

?>
<?= '<texta' . 'rea>'; ?>
<?= Obfuscation::obfuscate(file_get_contents('example.php')); ?>
<?= '</texta' . 'rea>'; ?>

    <hr/>
    <h2>Results:</h2>
    <hr/>
    <h3>Functions list:</h3>
    <hr/>
<?php
foreach (Obfuscation::$functions as $funkey => $function) {
    echo $function . '() => ' . Obfuscation::$replace_functions[$funkey] . "()<br/>";
}
?>
    <h3>Variables list:</h3>
    <hr/>
<?php
foreach (Obfuscation::$variables as $varkey => $variable) {
    echo $variable . ' => $' . Obfuscation::$replace_variables[$varkey] . "<br/>";
}
?>