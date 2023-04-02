<?php
// Table extension, https://github.com/GiovanniSalmeri/yellow-table

class YellowTable {
    const VERSION = "0.8.18";
    public $yellow;         //access to API

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("tableDirectory", "media/tables/");
        $this->yellow->system->setDefault("tableHeadingStyle", "plain");
        $this->yellow->system->setDefault("tableTextStyling", "1");
    }

    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        $output = null;
        if ($name=="table" && ($type=="block" || $type=="inline")) {
            list($file, $caption, $style, $files, $unique, $filter, $sort, $arrange) = $this->yellow->toolbox->getTextArguments($text);
            $filtrable = in_array("filtrable", explode(" ", $style));
            $path = $this->yellow->system->get("tableDirectory");
            if (!is_string_empty($file)) {
                $table = $this->getTable($path.trim($file));
            } elseif (!is_string_empty($files)) {
                $table = $this->joinTables(array_map(
                    function($toJoin) use ($path) {
                        return $this->mergeTables(array_map(
                            function($toMerge) use ($path) {
                                return $this->getTable($path.trim($toMerge));
                            }, explode(">", $toJoin))
                        ); 
                    }, explode("|", $files))
                );
                if (!is_string_empty($unique)) {
                    $table = $this->simplifyTable($table, $unique);
                }
                if (!is_string_empty($filter)) {
                    if ($or = ($filter[0]=="|")) $filter = substr($filter, 1);
                    $filters = array_map(
                        function($i) { 
                            if (preg_match('/^(.+)(<<|==|>>|>=|!=|<=)(.+)$/', $i, $m)) return [ trim($m[1]), $m[2], trim($m[3]) ]; 
                        }, explode("|", $filter)
                    );
                    $table = $this->filterTable($table, $filters, $or);
                }
                if (!is_string_empty($sort)) {
                    $columns = array_map("trim", explode("|", $sort));
                    $table = $this->sortTable($table, $columns);
                }
                if (!is_string_empty($arrange)) {
                    $columns = array_map("trim", explode("|", $arrange));
                    $table = $this->arrangeTable($table, $columns);
                }
            }

            if ($table!==false) { // to M.
                $uid = uniqid();
                $output .= "<div class=\"table-container\" tabindex=\"0\" role=\"group\"".($caption ? " aria-labelledby=\"caption-".$uid."\"" : "").">\n";
                $output .= "<table";
                if ($filtrable) $output .= " id=\"".$uid."\"";
                if (!is_string_empty($style)) $output .= " class=\"".htmlspecialchars($style)."\"";
                $output .= ">\n";
                if ($caption || $filtrable) {
                    $output .= "<caption id=\"caption-".$uid."\">".htmlspecialchars($caption);
                    if ($filtrable) {
                        $output .= "<input role=\"search\" type=\"text\" class=\"filter form-control\" placeholder=\"".$this->yellow->language->getTextHtml("searchButton")."\" data-table=\"".htmlspecialchars($uid)."\" />";
                    }
                    $output .= "</caption>\n";
                }
                $output .= "<thead>\n<tr>".implode("", array_map(function ($cell) use ($page) { return $this->HTMLHeaderCell($page, $cell); }, $table['columns']))."</tr>\n</thead>\n";
                $output .= "<tbody>\n";
                foreach ($table['data'] as $row) {
                    $output .= "<tr>".implode("", array_map(function ($cell) use ($page) { return $this->HTMLCell($page, $cell); }, $row))."</tr>\n";
                }
                $output .= "</tbody>\n</table>\n";
                $output .= "</div>\n";
            }
        }
        return $output;
    }

    // Create header cell from text
    private function HTMLHeaderCell($page, $text) {
        $text = trim($text);
        return "<th scope=\"col\">".($this->yellow->system->get("tableTextStyling") ? $this->parseText($page, $text) : htmlspecialchars($text))."</th>";
    }

    // Create cell from text
    private function HTMLCell($page, $text) {
        $NBSP = "\302\240";
        $text = trim($text); 
        if (is_numeric($text)) {
            $decimal = strpos($text, ".");
            return "<td class=\"num\">". ($decimal===false || $decimal===strlen($text)-1 ? number_format($text, 0, "", $NBSP) : number_format(substr($text, 0, $decimal), 0, "", $NBSP).".".chunk_split(substr($text, $decimal+1), 3, $NBSP)) ." </td>";
        } else {
            return "<td>".($this->yellow->system->get("tableTextStyling") ? $this->parseText($page, $text) : htmlspecialchars($text))." </td>"; // space before < is intentional!
        }
    }

    // Get table
    public function getTable($fileName) {
        $fileType = $this->yellow->toolbox->getFileType($fileName);
        if (in_array($fileType, [ "csv", "tsv", "psv" ])) {
            return $this->getDSV($fileName, $fileType);
        } elseif ($fileType==="func") {
            return $this->getFunctionFile($fileName);
        } else {
            return false;
        }
    }

    // Get DSV table
    private function getDSV($fileName, $fileType) {
        if (($fileHandle = @fopen($fileName, "r"))) {
            $this->skipBOM($fileHandle);
            $rows = [];
            if ($fileType=="csv") {
                $options = [ ",", "\"" ];
                $filePosition = ftell($fileHandle);
                if (($line = fgets($fileHandle))!==false) { // Check for Excel-style metadata
                    $line = stripcslashes(trim($line));
                    if (strlen($line)==5 && strtolower(substr($line, 0, 4))=="sep=") {
                        $options[0] = $line[4];
                    } else {
                        fseek($fileHandle, $filePosition);
                    }
                }
                while (($data = fgetcsv($fileHandle, 0, ...$options))!==false) {
                    $rows[] = array_map("trim", $data);
                }
            } else {
                $options = $this->getDSVOptions($fileType);
                while (($line = fgets($fileHandle))!==false) {
                    $rows[] = array_map(function($s) use ($options) { return strtr($s, $options[1]); }, explode($options[0], rtrim($line, "\n\r")));
                }
            }
            fclose($fileHandle);
            $columns = array_shift($rows);
            foreach ($rows as &$row) {
                $row = $this->array_equalise($row, count($columns));
            }
            return [ 'columns'=>$columns, 'data'=>$rows ];
        } else {
            return false;
        }
    }

    // Get virtual table
    private function getFunctionFile($fileName) {
        if (class_exists("YellowTableFunctions") && ($fileHandle = @fopen($fileName, "r"))) {
            $this->skipBOM($fileHandle);
            $data = [];
            while (($line = fgets($fileHandle))!==false) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches) && $matches[2]!=="") {
                    $data[strtolower($matches[1])] = $matches[2];
                }
            }
            fclose($fileHandle);
            $definition = [];
            $definition['function'] = $data["function"] ?? null;
            $definition['inputValues'] = isset($data['fixedarguments']) ? preg_split("/\s*,\s*/", $data['fixedarguments']) : [];
            $definition['inputColumns'] = isset($data['arguments']) ? preg_split("/\s*,\s*/", $data['arguments']) : [];
            $definition['columns'] = isset($data['values']) ? preg_split("/\s*,\s*/", $data['values']) : [];
            $definition['data'] = [ array_fill(0, count($definition['columns']), "? ".$definition["function"]) ]; // placeholder
            return $definition;
        } else {
            return false;
        }
    }

    // Put table
    public function putTable($fileName, $table) {
        if ($table===false) return false;
        $fileType = $this->yellow->toolbox->getFileType($fileName);
        if ($fileHandle = @fopen($fileName, "w")) {
            if(flock($fileHandle, LOCK_EX)) {
                array_unshift($table['data'], $table['columns']);
                if ($fileType=="csv") {
                    $options = [ ",", "\"" ];
                    foreach ($table['data'] as $row) {
                        fputcsv($fileHandle, $row, ...$options);
                    }
                } else {
                    $options = $this->getDSVOptions($fileType, true);
                    foreach ($table['data'] as $row) {
                        fwrite($fileHandle, implode($options[0], array_map(function($s) use ($options) { return strtr($s, $options[1]); }, $row))."\n");
                    }
                }
                flock($fileHandle, LOCK_UN);
            }
            fclose($fileHandle);
            return true;
        } else {
            return false;
        }
    }

    // Get DSV separator and escape table
    private function getDSVOptions($fileType, $encode = false) {
        $options = [
            "tsv"=>[ "\t", [ '\n'=>"\n", '\r'=>"\r", '\\'=>"\\", '\t'=>"\t" ] ],
            "psv"=>[ "|", [ '\n'=>"\n", '\r'=>"\r", '\\'=>"\\", '\p'=>"|" ] ]
            /* `\p` as escape of `|` is non-standard, but allows a naive parsing */
        ];
        return [ $options[$fileType][0], $encode ? array_flip($options[$fileType][1]) : $options[$fileType][1] ];
    }

    // Lock or unlock a table
    public function lockTable($fileName, $lock = true) {
        /*
        The function `putTable` already uses an advisory lock in order 
        to prevent two parallel instances of a script to write at once
        the same table. This function uses another kind of lock and is 
        convenient when a table is read, modified in memory, and then 
        overwritten. In these cases the table should be locked before 
        being read *and* unlocked after being written.
        */
        $maxLockTime = 10; // in seconds
        $lockName = $fileName.".lock";
        $lockFileTime = @filemtime($lockName);
        if ($lock) {
            while (!@fopen($lockName, "x")) {
                if (time()-$lockFileTime>$maxLockTime) @unlink($lockname);
                usleep(100000); // 1/10 s
            }
        } else {
            @unlink($lockName);
        }
    }

    // Join tables
    public function joinTables($tables) {
        $tables = array_filter($tables);
        if (count($tables)==0) return false;
        if (count($tables)==1) return $tables[0]; // speed up
        $master = array_shift($tables);
        foreach ($tables as $table) {
            $flippedMasterColumns = array_flip($master['columns']);
            if (isset($table['function'])) { // Virtual table
                $customFunctions = new YellowTableFunctions;
                $functionIsDefined = is_callable([$customFunctions, $table['function']]);
                foreach ($master['data'] as &$row) {
                    if ($functionIsDefined) {
                        $functionArguments = array_map(
                            function ($column) use ($row, $flippedMasterColumns) { 
                                return isset($flippedMasterColumns[$column]) ? 
                                    $row[$flippedMasterColumns[$column]] : 
                                    null; 
                            }, $table['inputColumns']
                        );
                        $row = array_merge(
                            $row, $this->array_equalise(call_user_func(
                                [$customFunctions, $table['function']], 
                                ...$table['inputValues'], ...$functionArguments
                            ), count($table['columns']))
                        );
                    } else {
                        $row = array_merge($row, $table['data'][0]); // placeholder
                    }
                }
                unset($row);
                $master['columns'] = array_merge($master['columns'], $table['columns']);
            } else { // Real table
                $linkColumn = $flippedMasterColumns[$table['columns'][0]] ?? null;
                if (isset($linkColumn)) {
                    $flippedColumns = array_flip(array_column($table['data'], 0));
                    $defaultValues = array_fill(0, count($table['columns'])-1, null);
                    foreach ($master['data'] as &$row) {
                        $pos = $flippedColumns[$row[$linkColumn]] ?? null;
                        $row = array_merge($row, 
                            isset($pos) ? array_slice($table['data'][$pos], 1) : $defaultValues
                        );
                    }
                    unset($row); // important
                    $master['columns'] = array_merge($master['columns'], array_slice($table['columns'], 1));
                }
            }
        }
        return $master;
    }

    // Merge tables (also with different columns)
    public function mergeTables($tables) {
        $tables = array_filter($tables);
        if (count($tables)==0) return false;
        if (count($tables)==1) return $tables[0]; // speed up and avoid munging a .func file
        $totalColumns = $this->array_union(...array_map(function($i) { return $i['columns']; }, $tables));
        $flippedColumns = array_flip($totalColumns);
        $totalRows = [];
        $voidRow = array_fill(0, count($totalColumns), null);
        foreach ($tables as $table) {
            if ($table['columns']===$totalColumns) { // speed up
                $totalRows = array_merge($totalRows, $table['data']);
            } else {
                foreach ($table['data'] as $row) {
                    $newRow = $voidRow;
                    foreach ($row as $index=>$value) {
                        $newRow[$flippedColumns[$table['columns'][$index]]] = $value;
                    }
                    $totalRows[] = $newRow;
                }
            }
        }
        return [ 'columns'=>$totalColumns, 'data'=>$totalRows ];
    }

    // Delete duplicates
    public function simplifyTable($table, $column) {
        if ($table===false) return false;
        $columnNumber = array_search($column, $table['columns']);
        if ($columnNumber!==false) {
            $existingKeys = [];
            $uniqueRows = [];
            foreach (array_reverse($table['data']) as $row) {
                if (!isset($existingKeys[$row[$columnNumber]])) {
                    $uniqueRows[] = $row;
                    $existingKeys[$row[$columnNumber]] = true;
                }
            }
            $table['data'] = array_reverse($uniqueRows);
        }
        return $table;
    }

    // Filter table (condition[0] column, condition[1] see below, condition[2] value)
    public function filterTable($table, $conditions, $or = false) {
        if ($table===false) return false;
        $conditions = array_filter($conditions);
        $condCodes = [
            "<<"=>[ 'cmp'=>-1, 'not'=>false ],
            "=="=>[ 'cmp'=>0, 'not'=>false ],
            ">>"=>[ 'cmp'=>1, 'not'=>false ],
            ">="=>[ 'cmp'=>-1, 'not'=>true ],
            "!="=>[ 'cmp'=>0, 'not'=>true ],
            "<="=>[ 'cmp'=>1, 'not'=>true ],
        ];
        $filteredRows = [];
        $flippedColumns = array_flip($table['columns']);
        foreach ($table['data'] as $row) {
            foreach ($conditions as $condition) {
                if (!isset($condCodes[$condition[1]])) continue;
                if ($or xor $condCodes[$condition[1]]['not'] xor (mb_convert_case($row[$flippedColumns[$condition[0]]], MB_CASE_UPPER)<=>mb_convert_case($condition[2], MB_CASE_UPPER))!==$condCodes[$condition[1]]['cmp']) {
                    if ($or) $filteredRows[] = $row;
                    continue 2;
                }
            }
            if (!$or) $filteredRows[] = $row;
        }
        return [ 'columns'=>$table['columns'], 'data'=>$filteredRows ];
    }

    // Sort table
    public function sortTable($table, $sortingColumns) {
        if ($table===false) return false;
        $sortingColumns = array_intersect($sortingColumns, $table['columns']);
        if ($sortingColumns) {
            $flippedColumns = array_flip($table['columns']);
            $columnIndexes = array_map(function($column) use ($flippedColumns) { return $flippedColumns[$column]; }, $sortingColumns);
            $memoisedLowercase = [];
            foreach ($table['data'] as $row) {
                foreach ($columnIndexes as $columnIndex) {
                    $memoisedLowercase[$row[$columnIndex]] = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $row[$columnIndex]));
                }
            }
            usort($table['data'], function($a, $b) use ($columnIndexes, $memoisedLowercase) {
                foreach ($columnIndexes as $columnIndex) {
                    if (($cmp = ($memoisedLowercase[$a[$columnIndex]]<=>$memoisedLowercase[$b[$columnIndex]]))!==0) return $cmp;
                }
                return 0;
            });
        }
        return $table;
    }

    // Reduce or reorder columns of table
    public function arrangeTable($table, $columns) {
        if ($table===false) return false;
        $columns = array_intersect($columns, $table['columns']);
        $flippedColumns = array_flip($table['columns']);
        $columnIndexes = array_map(function($column) use ($flippedColumns) { return $flippedColumns[$column]; }, $columns);
        foreach ($table['data'] as &$row) {
            $arrangedRow = [];
            foreach ($columnIndexes as $columnIndex) {
                $arrangedRow[] = $row[$columnIndex];
            }
            $row = $arrangedRow;
        }
        unset($row);
        return [ 'columns'=>$columns, 'data'=>$table['data'] ];
    }

    // Helper function: Format markdown
    private function parseText($page, $text, $singleLine = true) {
        $parser = $this->yellow->extension->get($this->yellow->system->get("parser"));
        $output = $parser->onParseContentRaw($page, $text);
        if ($singleLine) {
            if (substr($output, 0, 3)=="<p>" && substr($output, -5)=="</p>\n") $output = substr($output, 3, -5);
        }
        return $output;       
    }

    // Helper function: skip BOM    
    private function skipBOM($fileHandle) {
        if (fgets($fileHandle, 4)!=="\xef\xbb\xbf") rewind($fileHandle);
    }

    // Helper function: array union
    private function array_union(...$arrays) {
        return array_values(array_unique(array_merge(...$arrays)));
    }

    // Helper function: truncate or pad an array to a given length
    private function array_equalise($array, $length) {
        $array = (array)$array;
        if (count($array)==$length) {
            return $array;
        } elseif (count($array)<$length) {
            return array_pad($array, $length, null);
        } elseif (count($array)>$length) {
            return array_slice($array, 0, $length);
        }
    }

    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $extensionLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreExtensionLocation");
            $style = $this->yellow->system->get("tableHeadingStyle");
            $output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$extensionLocation}table-{$style}.css\" />\n";
            $output .= "<script type=\"text/javascript\" defer=\"defer\" src=\"{$extensionLocation}table.js\"></script>\n";
        }
        return $output;
    }
}
