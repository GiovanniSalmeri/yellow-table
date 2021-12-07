<?php
// YellowTableFunctions class, https://github.com/GiovanniSalmeri/yellow-table

class YellowTableFunctions {

    /*
    Here you can define functions to be used in virtual tables with 
    the extension YellowTable. For example, if a `sum` function is 
    defined as below you can write a `gross.func` file with the 
    following content:

        Function: sum
        Arguments: Net Weight, Tare Weight
        Values: Gross Weight

    If you join this virtual table to a table containing the columns 
    `Net Weight` and `Tare Weight` (e.g. with the shortcut `[table 
    wares.csv|gross.func]`), a column `Gross Weight` will be added.

    Do not ever allow anyone to edit freely this file and review 
    carefully for correctness every new function added. **One single 
    syntax error in this file will prevent the whole site from 
    functioning.** In no case should a function here defined have 
    side-effects (assignement of global variables, modification of 
    files, output to the screen).

    Some commented examples follow.
    */

    public function sum($a, $b) {
        /* One value can be returned as a scalar */
        /* `return [ $a+$b ]` would also be fine */
        return $a+$b;
    }

    public function sumAndDifference($a, $b) {
        /* More values must be returned as an array with the [] notation */
        return [ $a+$b, $a-$b ];
    }

    public function sumAll(...$array) {
        /* The ... notation allows to use the function with any number of arguments */
        return array_sum($array);
    }

    public function upperCase(...$array) {
        /* Return one or more strings in uppercase (conversion is Unicode-aware) */
        /* The returned values are as many as the arguments */
        /* Since `array_map` returns an array, the [] notations *must not* be used */
        return array_map(function($string) { return mb_convert_case($string, MB_CASE_UPPER); }, $array);
    }

    public function mixedCase(...$array) {
        /*
        Return one or more strings in mixed case (e.g. Jean-Paul Sartre).
        This is an approximation to the way proper names are usually 
        written If you want to replace an uppercase name with its 
        mixed-case counterpart, you can simply join this `.func` file 
        (the second occurrence of Name replace the first):

            Function: mixedCase
            Arguments: Name
            Values: Name
	*/
        return array_map(function($string) { 
            return preg_replace_callback('/(?<=\s|-|\'|^)\pL/u', function($matches) { 
                return mb_convert_case($matches[0], MB_CASE_UPPER); 
            }, mb_convert_case($string, MB_CASE_LOWER)); 
        }, $array);
    }

    public function elapsedYears($date) {
        /* If a birthdate is given, this function returns the age */
        /* The date can be specified in any sensible format (e.g. YYYY-MM-DD, DD.MM.YYYY...) */
        /* but if you use slashes 10/12/2000 is 12 October 2000! */
        /* https://www.php.net/manual/en/datetime.formats.date.php for further informations */
        return date_diff(date_create($date), date_create())->y;
    }

    public function metaphone($name) {
        /* Return the metaphone key of a name */
        /* https://www.php.net/manual/en/function.metaphone.php for further informations */
        /* This is a trivial wrapper around the standard function with the same name */
        return metaphone($name);
    }

    public function italianFiscalCode($minimumAge, $code) {
        /*
        Extract from Italian Fiscal Code the informations about the 
        owner: birthdate, sex, birthplace code (Italian city or foreign 
        country). Italian Fiscal Code is sometimes used in databases in 
        substitution of an explicit mentioning of these data. Because 
        the year of birth is coded with only the two least meaningful 
        figures, there is no way to know whether the owner of a certain 
        Fiscal Code is n or n+100 years old. `$minimumAge` must be passed 
        as FixedArgument in order to state what is the minimum (and 
        maximum) age represented in your table: if you pass 0, ages will 
        be interpreted in the interval 0-99.

        The file `italian_birthplace_codes.csv` can be joined in order 
        to add a column with the name of the birthplace. It contains all 
        codes ever used, even when they refer to municipalities or 
        countries not existing anymore. 
        */

        foreach ([6, 7, 9, 10, 12, 13, 14] as $pos) { // Normalise codes altered for avoiding collisions
            if (($numeric = strpos("LMNPQRSTUV", $code[$pos]))!==false) {
                $code[$pos] = (string)$numeric;
            }
        }
        $year = (int)substr($code, 6, 2);
        $month = strpos("ABCDEHLMPRST", substr($code, 8, 1))+1;
        $day = substr($code, 9, 2) % 40;
        $sex = substr($code, 9, 2) > 40 ? "F" : "M";
        $birthPlaceCode = substr($code, 11, 4);
        $now = getdate();
        $century = (int)floor($now['year']/100);
        if ([ $year, $month, $day ] < [ $now['year']%100-$minimumAge, $now['mon'], $now['mday'] ]) {
            $year += $century*100;
        } else {
            $year += ($century-1)*100;
        }
        $birthDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
        return [ $birthDate, $sex, $birthPlaceCode ];
    }

    public function getFromTable($fileName, $keyColumn) {
        /*
        Return from a table in the TableDirectory the values from the 
        2nd to the last columns in the row whose 1st column has the 
        value `$keyColumn`. In this way you can join this table using 
        names other than those written in its header. The bridge 
        `func.` file must be written like this:

            Function: getFromTable
            FixedArguments: table_file_name.csv
            Arguments: InputColumn
            Values: Column1, Column2, ...

        */

        static $currentTable = null;
        static $table = null;
        static $flippedKeys = null;

        if ($currentTable!==$fileName) {
            $currentTable = $fileName;
            $path = $this->yellow->system->get("tableDirectory");
            $table = $this->yellow->extension->get("table")->getTable($path.$fileName);
            $flippedKeys = array_flip(array_column($table['data'], 0));
        }
        $pos = $flippedKeys[$keyColumn] ?? null;
        if (isset($pos)) {
            return array_slice($table['data'][$pos], 1);
        } else {
            return array_fill(0, count($table['columns'])-1, null);
        }
    }

}
