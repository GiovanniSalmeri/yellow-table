Table 0.8.18
============
Simple flat-file database management.

<p align="center"><img src="table-screenshot.png?raw=true" alt="Screenshot"></p>

## How to use Table

Create a [table] shortcut.

The following arguments are available, all but the first argument are optional:

`Name` = file name (`.csv`, `.tsv`, and `.psv` types are recognised)  
`Caption` = caption of the table  
`Style` = table style, e.g. `left`, `center`, `right`; the special styles `sortable`, `filtrable` and `point-aligned` add the respective function to the table  

If `Name` is left empty (i.e. `-`), the following advanced arguments are also available, among which the first is mandatory:

`Names` = one file name, or more files to be "merged", concatenated by `>` (e.g. `members2019.csv>members2020.csv`), or to be "joined", concatenated by `|` (e.g. `people.csv|cities.psv`); merging has higher precedence  
`Unique` = remove duplicate rows, i.e. which have the same value in the column specified, e.g. `"Identification code"`  
`Filter` = show only the rows which satisfy one or more conditions, e.g. `Country==Italy`; available operators are `<<`, `==`, `>>`, `>=`, `!=`, `>=`; alphabetic comparison are case-insensitive; more conditions can be concatenated with `|`, e.g. `Country==Italy|Date<<2020-10-07`; a leading `|` signals that conditions are to be or'ed (rather than and'ed), e.g. `|Country==Italy|Date<<2020-10-07`  
`Sort` = sort the table by one or more columns, e.g. `Surname|Name`; the sorting is case-insensitive  
`Columns` = show only some columns, in a certain order, e.g. `Country|City|Year`  

An option must be wrapped into quotes if a space is present, e.g. `"Name|Surname|Date of birth"`. If you wish to omit an option but specify the following ones, write `-`.

## How to use Table from other extensions

Besides showing a table, this extension provides developers with simple database capabilities for their extensions. The following functions are exposed:

`getTable($fileName)`  
Return a table from a recognised file type

`putTable($fileName, $table)`  
Save a table in a recognised file type

`lockTable($fileName, $lock = true)`  
Create (or delete, if `$lock` is `false`) a lock, which prevents another instance of the script to access simultaneously `$fileName`

`joinTables($tables)`  
Return a table which is the result of the join operation on an array of tables, using the first column as key (e.g. you can join two tables with the columns `Name, Surname, City, Age` and `City, State`: the resulting one will have the columns `Name, Surname, City, Age, State`); moreover virtual `.func` tables can be joined to real ones  

`mergeTables($tables)`  
Return a table which is the result of the merge operation on an array of tables, even with different columns

`simplifyTable($tables, $column)`  
Return a table without duplicate rows, i.e. those whose `$column` is identical (only the last one is kept)

`filterTable($table, $conditions, $or = false)`  
Return a table with only the rows which match the conditions; conditions are in the format `[[$columnName1, "OP", $value1], [$columnName2, "OP", $value2], ...]`, where OP is one of `<<`, `==`, `>>`, `>=`, `!=`, `>=`; set `$or` to `true` for the conditions to be or'ed rather than and'ed

`sortTable($table, $columns)`  
Sort a table according to the columns specified, in ascending order

`arrangeTable($table, $columns)`  
Return a table with only the columns and in the order specified

## How to define a table from other extensions

`$table` is an associative array. It can be initialised from a file with `getTable`, or assigned a value and modified with standard PHP operations. Its structure is as follows:

    [
        "columns"=>[ "Column1", "Column2", "Column3", ... ],
        "data"=>[
            [ "Value1", "Value2", "Value3", ... ],
            [ "Value1", "Value2", "Value3", ... ],
            ...
        ]
    ]

The values of each row must be as many as the columns.

## Example

Showing a table:

    [table data.csv]
    [table data.csv "European countries" filtrable]
    [table data.tsv "European countries" "sortable center"]

For numbers of a column to be aligned at the decimal point `.`, add the `point-aligned` style (or ensure that all of them have the same number of decimals). Four-digit integers (e.g. years) are shown with no thousand separator; in order to force it, add to them a decimal point. Dates are properly sortable if they are in ISO format `YYYY-MM-DD`.

Showing a table with some advanced options:

    [table - "Society members" - members2020.csv>members2021.csv "Personal Id" - "Surname"]
    [table - "Recent students" filtrable students.psv|curricula.csv - "Inscription Year==2021"]

Reading a table from an extension:

    $tableHandler = $this->yellow->extension->get("table");
    $path = $this->yellow->system->get("tableDirectory");
    $fileName = "my-table.csv";
    $table = $tableHandler->getTable($path.$fileName);

Table file in `.csv` format:

    Country, Capital, Population
    Austria, Vienna, 8857960
    Belgium, Brussels, 11449656
    Denmark, Copenhagen, 5806015

The separator is `,`, unless a different one is specified in a line, put at the beginning of the file before the headers, containing only `sep=` followed by the separator, e.g. `sep=;`, `sep=\t`. A column containing the separator character must be wrapped into quotes `"`. Files in `.tsv` and `.psv` format have tabs and pipes (`|`) as separators respectively, use no quotes, but can escape the separator (as `\t` and `\p` respectively).

Besides real tables, you can join virtual `.func` tables. They specify calculated columns with this format:

    Function: FunctionName
    FixedArguments: Value1, Value2 ...
    Arguments: InputColumn1, InputColumn2 ...
    Values: Column1, Column2 ...

FixedArguments, if present, are passed to the function before other arguments. Functions can be defined in the file `system/extensions/tablefunctions.php`.

## Settings

The following settings can be configured in file `system/settings/system.ini`:

`TableDirectory` (default = `media/tables/`) = base directory for tables  
`TableHeadingStyle` (default = `plain`) = heading style for sortable tables (you can choose between `plain` and `link`)    

If you want to add a new `fancy` heading style, write a `table-fancy.css`  file and put into the `system/extensions` folder (since Yellow's themes do not provide a `caption` declaration, you may want to adjust it in order to blend it with your theme).

## Installation

[Download extension](https://github.com/GiovanniSalmeri/yellow-table/archive/main.zip) and copy zip file into your `system/extensions` folder. Right click if you use Safari.

## Developer

Giovanni Salmeri. [Get help](https://datenstrom.se/yellow/help/)
