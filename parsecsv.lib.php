<?php
// Test with PHPStorm
/**
 * Contains the parseCSV, CSVReaderRow, and CSVReaderException classes.
 *
 * Dependencies:
 * <pre>
 * PHP 5.3 or higher
 * </pre>
 *
 * TODO: Treat all streams as non-seekable to reduce code complexity.
 * TODO: Handle transcoding errors so that they are more informative with line number etc.
*/



/**
 * Custom Exception class.
*/
class CSVReaderException extends Exception {}


/**
 * CSV file/stream reader class that implements the Iterator interface.
 * It supports files having a (UTF-8 or UTF-*) BOM, as well as non-seekable streams.
 *
 * Example(s):
 * <pre>
 *	ini_set('auto_detect_line_separators', 1); // Only necessary if CSVReader is having trouble detecting line separators in MAC files.
 *
 *	// read a plain CSV file
 *	$reader = new CSVReader('products.csv');
 *
 *	// read a gzipped CSV file
 *	$reader = new CSVReader('compress.zlib://products.csv.gz');
 *
 *	// read CSV from STDIN
 *	$reader = new CSVReader('php://stdin');
 *
 *	// read products.csv from within export.zip archive
 *	$reader = new CSVReader('zip://export.zip#products.csv');
 *
 *	// read from a open file handle
 *	$h = fopen('products.csv', 'r');
 *	$reader = new CSVReader($h);
 *
 *	// Show fieldnames from 1st row:
 *	print_r($reader->fieldNames());
 *
 *	// Iterate over all the rows using foreach. CVSReader behaves as an array.
 *	foreach($reader as $row) { // $row is a CSVReaderRow object
 *		print 'Price: ' . $row->get('Price') . "\n";
 *		print 'Name: ' . $row['Name'] . "\n"; // $row also supports array access
 *		print_r($row->toArray());
 *		// TIP: Use PHP-Validate, by your's truly, to validate $row->toArray()
 *	}
 *
 *	// Iterate over all the rows using while.
 *	while ($reader->valid()) {
 *		$row = $reader->current(); // CSVReaderRow object
 *		print 'Price: ' . $row->get('Price') . "\n";
 *		print 'Name: ' . $row['Name'] . "\n"; // $row also supports array access
 *		print_r($row->toArray());
 *		// TIP: Use PHP-Validate, by your's truly, to validate $row->toArray()
 *		$reader->next();
 *	}
 * </pre>
 */
class parseCSV implements Iterator {

    /*
    Class: parseCSV v0.4.3 beta
    https://github.com/parsecsv/parsecsv-for-php

    Fully conforms to the specifications lined out on wikipedia:
     - http://en.wikipedia.org/wiki/Comma-separated_values

    Based on the concept of Ming Hong Ng's CsvFileParser class:
     - http://minghong.blogspot.com/2006/07/csv-parser-for-php.html


    (The MIT license)

    Copyright (c) 2014 Jim Myhrberg.

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.


    Code Examples
    ----------------
    # general usage
    $csv = new parseCSV('data.csv');
    print_r($csv->data);
    ----------------
    # tab delimited, and encoding conversion
    $csv = new parseCSV();
    $csv->encoding('UTF-16', 'UTF-8');
    $csv->delimiter = "\t";
    $csv->parse('data.tsv');
    print_r($csv->data);
    ----------------
    # auto-detect delimiter character
    $csv = new parseCSV();
    $csv->auto('data.csv');
    print_r($csv->data);
    ----------------
    # modify data in a csv file
    $csv = new parseCSV();
    $csv->sort_by = 'id';
    $csv->parse('data.csv');
    # "4" is the value of the "id" column of the CSV row
    $csv->data[4] = array('firstname' => 'John', 'lastname' => 'Doe', 'email' => 'john@doe.com');
    $csv->save();
    ----------------
    # add row/entry to end of CSV file
    #  - only recommended when you know the extact sctructure of the file
    $csv = new parseCSV();
    $csv->save('data.csv', array(array('1986', 'Home', 'Nowhere', '')), true);
    ----------------
    # convert 2D array to csv data and send headers
    # to browser to treat output as a file and download it
    $csv = new parseCSV();
    $csv->output('movies.csv', $array, array('field 1', 'field 2'), ',');
    ----------------
    */

    /**
     * Configuration
     * - set these options with $object->var_name = 'value';
     */

    /**
     * Heading
     * Use first line/entry as field names
     *
     * @access public
     * @var bool
     */
    public $heading = true;

    /**
     * Fields
     * Override field names
     *
     * @access public
     * @var array
     */
    public $fields = array();

    /**
     * Sort By
     * Sort csv by this field
     *
     * @access public
     * @var string
     */
    public $sort_by = null;

    /**
     * Sort Reverse
     * Reverse the sort function
     *
     * @access public
     * @var bool
     */
    public $sort_reverse = false;

    /**
     * Sort Type
     * Sort behavior passed to sort methods
     *
     * regular = SORT_REGULAR
     * numeric = SORT_NUMERIC
     * string  = SORT_STRING
     *
     * @access public
     * @var string
     */
    public $sort_type = null;

    /**
     * Delimiter
     * Delimiter character
     *
     * @access public
     * @var string
     */
    public $delimiter = ',';

    /**
     * Enclosure
     * Enclosure character
     *
     * @access public
     * @var string
     */
    public $enclosure = '"';

    /**
     * Enclose All
     * Force enclosing all columns
     *
     * @access public
     * @var bool
     */
    public $enclose_all = false;

    /**
     * Conditions
     * Basic SQL-Like conditions for row matching
     *
     * @access public
     * @var string
     */
    public $conditions = null;

    /**
     * Offset
     * Number of rows to ignore from beginning of data
     *
     * @access public
     * @var int
     */
    public $offset = null;

    /**
     * Limit
     * Limits the number of returned rows to the specified amount
     *
     * @access public
     * @var int
     */
    public $limit = null;

    /**
     * Auto Depth
     * Number of rows to analyze when attempting to auto-detect delimiter
     *
     * @access public
     * @var int
     */
    public $auto_depth = 15;

    /**
     * Auto Non Charts
     * Characters that should be ignored when attempting to auto-detect delimiter
     *
     * @access public
     * @var string
     */
    public $auto_non_chars = "a-zA-Z0-9\n\r";

    /**
     * Auto Preferred
     * preferred delimiter characters, only used when all filtering method
     * returns multiple possible delimiters (happens very rarely)
     *
     * @access public
     * @var string
     */
    public $auto_preferred = ",;\t.:|";

    /**
     * Convert Encoding
     * Should we convert the csv encoding?
     *
     * @access public
     * @var bool
     */
    public $convert_encoding = false;

    /**
     * Input Encoding
     * Set the input encoding
     *
     * @access public
     * @var string
     */
    public $input_encoding = 'ISO-8859-1';

    /**
     * Output Encoding
     * Set the output encoding
     *
     * @access public
     * @var string
     */
    public $output_encoding = 'ISO-8859-1';

    /**
     * Linefeed
     * Line feed characters used by unparse, save, and output methods
     *
     * @access public
     * @var string
     */
    public $linefeed = "\r";

    /**
     * Output Delimiter
     * Sets the output delimiter used by the output method
     *
     * @access public
     * @var string
     */
    public $output_delimiter = ',';

    /**
     * Output filename
     * Sets the output filename
     *
     * @access public
     * @var string
     */
    public $output_filename = 'data.csv';

    /**
     * Keep File Data
     * keep raw file data in memory after successful parsing (useful for debugging)
     *
     * @access public
     * @var bool
     */
    public $keep_file_data = false;

    /**
     * Internal variables
     */

    /**
     * File
     * Current Filename
     *
     * @access public
     * @var string
     */
    public $file;

    /**
     * File Data
     * Current file data
     *
     * @access public
     * @var string
     */
    public $file_data;

    /**
     * Error
     * Contains the error code if one occured
     *
     * 0 = No errors found. Everything should be fine :)
     * 1 = Hopefully correctable syntax error was found.
     * 2 = Enclosure character (double quote by default)
     *     was found in non-enclosed field. This means
     *     the file is either corrupt, or does not
     *     standard CSV formatting. Please validate
     *     the parsed data yourself.
     *
     * @access public
     * @var int
     */
    public $error = 0;

    /**
     * Error Information
     * Detailed error information
     *
     * @access public
     * @var array
     */
    public $error_info = array();

    /**
     * Titles
     * CSV titles if they exists
     *
     * @access public
     * @var array
     */
    public $titles = array();

    /**
     * Data
     * Two dimensional array of CSV data
     *
     * @access public
     * @var array
     */
    public $data = array();
    
    // from CSVReader
    protected $h; // File handle.
    protected $own_h; // Does this class own the file handle.
    protected $seekable; // Is the stream seekable?
    protected $initial_lines = array(); // For non-seekable streams: pre-read lines for inspecting BOM and encoding.
    protected $initial_lines_index = 0; // For non-seekable streams: index of which pre-read lines to read next. Used by _fgetcsv().
    protected $bom_len = 0; // Contains the BOM length.
    protected $field_cols = array(); // Associative array of fieldname => column index pairs.
    protected $row; // Current CSVReaderRow object.
    protected $key = -1; // Data row index.
    protected $must_transcode = false; // true if file encoding does not match internal encoding.
    
    // Options:
    protected $debug = false;
    protected $file_encoding = null;
    protected $internal_encoding = null;
    protected $skip_empty_lines = false;
    protected $length = 4096;
    protected $escape = '\\';
    protected $line_separator; // For UTF-16LE it's multibyte, e.g. 0x0A00
    
    // new and merged
    /**
     * Read Mode
     * Set whether files should be read as a whole or piece by piece for systems with low memory
     * Possible values: ["whole", "chunked"]
     *
     * @access public
     * @var string
     */
    public $read_mode = 'whole';
    
    /**
     * Write Mode
     * Set whether files should be written as a whole or piece by piece for systems with low memory
     * Possible values: ["whole", "chunked"]
     *
     * @access public
     * @var string
     */
    public $write_mode = 'whole';
    
    /**
     * Position
     * Iterator position in $this->data
     *
     * @access protected
     * @var int
     */
    public $position = 0;


    /**
     * Constructor
     * Class constructor
     *
     * @access public
     * @param  [string]  input      The CSV string or a direct filepath
     * @param  [integer] offset     Number of rows to ignore from the beginning of  the data
     * @param  [integer] limit      Limits the number of returned rows to specified amount
     * @param  [string]  conditions Basic SQL-like conditions for row matching
     */
    public function __construct ($input = null, $offset = null, $limit = null, $conditions = null, $keep_file_data = null, $read_mode = null, $write_mode = null) {
        if (!is_null($offset)) {
            $this->offset = $offset;
        }

        if (!is_null($limit)) {
            $this->limit = $limit;
        }

        if (!is_null($conditions)) {
            $this->conditions = $conditions;
        }

        if (!is_null($keep_file_data)) {
        	$this->keep_file_data = $keep_file_data;
        }

        if (!is_null($input)) {
            $this->parse($input);
        }
    }
    
    /**
     * alternative Constructor for CSVReader.
     *
     * @param string|resource $file file name or handle opened for reading.
     * @param array $option optional associative array of any of these options:
     *	- debug: boolean, if true, then debug messages are emitted using error_log().
     *	- field_aliases: associative array of case insensitive field name alias (in file) => real name (as expected in code) pairs.
     *	- field_normalizer: optional callback that receives a field name by reference to normalize (e.g. make lowercase).
     *	- include_fields: optional array of field names to include. If given, then all other field names are excluded.
     *	- file_encoding, default null, which means guess encoding using BOM or mb_detect_encoding().
     *	- internal_encoding, default is mb_internal_encoding(), only effective if 'file_encoding' is given or detected.
     *	- length: string, default 4096, see stream_get_line()
     *	- delimiter: string, guessed if not given with default ',', see str_getcsv()
     *	- enclosure: string, guessed if not given with default '"', see str_getcsv()
     *	- escape: string, default backslash, see str_getcsv()
     *	- line_separator: string, if not given, then it's guessed.
     *	- skip_empty_lines, default false
     * @throws CSVReaderException
     * @throws \InvalidArgumentException
     */
    public function ReaderConstruct($file, array $options = null) {
        // for backwards compatibility
        $this->delimiter = null;
        $this->enclosure = null;
        
        if (is_string($file)) {
            if (($this->h = fopen($file, 'r')) === FALSE) {
                throw new CSVReaderException('Failed to open "' . $file . '" for reading');
            }
            $this->own_h = true;
        }
        elseif (is_resource($file)) {
            $this->h = $file;
            $this->own_h = false;
        }
        else {
            throw new \InvalidArgumentException(gettype($file) . ' is not a legal file argument type');
        }
        if (1) {
            $meta = stream_get_meta_data($this->h);
            $this->seekable = $meta['seekable'];
            unset($meta);
        }
        if (!is_array($options)) {
            $options = array();
        }
    
        // Get the options.
        $opt_field_aliases = null;
        $opt_field_normalizer = null;
        $opt_include_fields = null;
        if ($options) {
            foreach ($options as $key => $value) {
                if (in_array($key, array('debug', 'skip_empty_lines'))) {
                    if (!(is_null($value) || is_bool($value) || is_int($value))) {
                        throw new \InvalidArgumentException("The '$key' option must be a boolean");
                    }
                    $this->$key = $value;
                }
                elseif (in_array($key, array('enclosure', 'escape', 'line_separator'))) {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException("The '$key' option must be a string");
                    }
                    $this->$key = $value;
                }
                elseif (in_array($key, array('delimiter', 'file_encoding', 'internal_encoding'))) {
                    if (!(is_string($value) && strlen($value))) {
                        throw new \InvalidArgumentException("The '$key' option must be a non-empty string");
                    }
                    $this->$key = $value;
                }
                elseif ($key == 'length') {
                    if (!(is_int($value) && ($value > 0))) {
                        throw new \InvalidArgumentException("The '$key' option must be positive int");
                    }
                    $this->$key = $value;
                }
                elseif ($key == 'include_fields') {
                    if (!is_array($value)) {
                        throw new \InvalidArgumentException("The '$key' option must be an array");
                    }
                    $opt_include_fields = $value;
                }
                elseif ($key == 'field_aliases') {
                    if (!is_array($value)) {
                        throw new \InvalidArgumentException("The '$key' option must be an associative array");
                    }
                    $opt_field_aliases = $value;
                }
                elseif ($key == 'field_normalizer') {
                    if (!is_callable($value)) {
                        throw new \InvalidArgumentException("The '$key' option must be callable, such as a closure or a function name");
                    }
                    $opt_field_normalizer = $value;
                }
                else {
                    throw new \InvalidArgumentException("Unknown option '$key'");
                }
            }
        }
        if (!$this->internal_encoding) {
            $this->internal_encoding = mb_internal_encoding();
        }
        $this->debug && error_log(__METHOD__ . ' Internal encoding: ' . $this->internal_encoding);
        $this->debug && error_log(__METHOD__ . ' File: ' . (is_string($file) ? $file : gettype($file)));
        $this->debug && error_log(__METHOD__ . ' Stream is seekable: ' . var_export($this->seekable,1));
    
        // Read the BOM, if any.
        if (1) {
            $line = fread($this->h, 4); // incomplete line!
            if ($line === false) {
                throw new CSVReaderException('No data found in CSV stream');
            }
            if ($first4 = substr($line,0,4)) {
                $first3 = substr($first4,0,3);
                $first2 = substr($first3,0,2);
                if ($first3 == chr(0xEF) . chr(0xBB) . chr(0xBF)) {
                    $this->file_encoding = 'UTF-8';
                    $this->bom_len = 3;
                }
                elseif ($first4 == chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF)) {
                    $this->file_encoding = 'UTF-32BE';
                    $this->bom_len = 4;
                }
                elseif ($first4 == chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00)) {
                    $this->file_encoding = 'UTF-32LE';
                    $this->bom_len = 4;
                }
                elseif ($first2 == chr(0xFE) . chr(0xFF)) {
                    $this->file_encoding = 'UTF-16BE';
                    $this->bom_len = 2;
                }
                elseif ($first2 == chr(0xFF) . chr(0xFE)) {
                    $this->file_encoding = 'UTF-16LE';
                    $this->bom_len = 2;
                }
            }
            if ($this->seekable) {
                if (fseek($this->h, $this->bom_len) != 0) {
                    throw new \Exception('Failed to fseek to ' . $this->bom_len);
                }
            }
            else {
                if (!$this->line_separator && $this->bom_len && ($this->file_encoding != 'UTF-8')) { // A string with multibyte line separators. Can't use fgets() here because it doesn't support multibyte line separators.
                    if ($this->file_encoding = 'UTF-16LE') {
                        $this->line_separator = "\x0A\x00";
                        $line .= stream_get_line($this->h, $this->length, $this->line_separator);
                        if (substr($line, -2) == "\x0D\x00") {
                            $this->line_separator = "\x0D\x00" . $this->line_separator;
                            $line = substr($line, 0, strlen($line) - 2);
                        }
                    }
                    elseif ($this->file_encoding = 'UTF-16BE') {
                        $this->line_separator = "\x00\x0A";
                        $line .= stream_get_line($this->h, $this->length, $this->line_separator);
                        if (substr($line, -2) == "\x00\x0D") {
                            $this->line_separator = "\x00\x0D" . $this->line_separator;
                            $line = substr($line, 0, strlen($line) - 2);
                        }
                    }
                    elseif ($this->file_encoding = 'UTF-32LE') {
                        $this->line_separator = "\x0A\x00\x00\x00";
                        $line .= stream_get_line($this->h, $this->length, $this->line_separator);
                        if (substr($line, -4) == "\x0D\x00\x00\x00") {
                            $this->line_separator = "\x0D\x00\x00\x00" . $this->line_separator;
                            $line = substr($line, 0, strlen($line) - 4);
                        }
                    }
                    elseif ($this->file_encoding = 'UTF-32BE') {
                        $this->line_separator = "\x00\x00\x00\x0A";
                        $line .= stream_get_line($this->h, $this->length, $this->line_separator);
                        if (substr($line, -4) == "\x00\x00\x00\x0D") {
                            $this->line_separator = "\x00\x00\x00\x0D" . $this->line_separator;
                            $line = substr($line, 0, strlen($line) - 4);
                        }
                    }
                    else {
                        throw new Exception('Line ending detection for file encoding ' . $this->file_encoding . ' not implemented yet.');
                    }
                }
                else {
                    $line .= stream_get_line($this->h, $this->length);
                }
                $this->initial_lines []= $this->bom_len ? substr($line, $this->bom_len) : $line;
            }
            unset($line, $first4, $first3, $first2);
            if ($this->debug) {
                if ($this->bom_len) {
                    error_log(__METHOD__ . ' BOM length: ' . $this->bom_len);
                    error_log(__METHOD__ . ' BOM file encoding: ' . $this->file_encoding);
                    error_log(__METHOD__ . ' Guessed line separator: ' . ' (0x' . bin2hex($this->line_separator) . ')');
                }
                else {
                    error_log(__METHOD__ . ' File has no BOM.');
                }
            }
        }
    
        // Guess some options if necessary by sniffing a chunk of data from the file.
        if (is_null($this->delimiter) || is_null($this->enclosure) || !$this->file_encoding || !$this->line_separator) {
            // Read some (more) lines from the input to use for inspection.
            $s = null;
            if ($this->seekable) {
                $s = fread($this->h, 16384);
                if (fseek($this->h, $this->bom_len) != 0) {
                    throw new \Exception('Failed to fseek to ' . $this->bom_len);
                }
            }
            else {
                $i = 0;
                while (($line = stream_get_line($this->h, $this->length, $this->line_separator)) !== false) {
                    $this->initial_lines []= $line;
                    if (++$i > 100) {
                        break;
                    }
                }
                $s = join($this->line_separator ? $this->line_separator : "\n", $this->initial_lines);
            }
            // If delimiter or enclosure are not given, try to guess them.
            if (is_null($this->delimiter) || is_null($this->enclosure)) {
                $guess = static::csv_guess($s, $this->file_encoding);
                if (is_null($this->delimiter)) {
                    if (is_string($guess['delimiter'])) {
                        $this->delimiter = $guess['delimiter'];
                        $this->debug && error_log(__METHOD__ . ' Guessed delimiter: ' . $this->delimiter . ' (0x' . bin2hex($this->delimiter) . ')');
                    }
                    else {
                        $this->delimiter = ',';
                        $this->debug && error_log(__METHOD__ . ' Default delimiter: ' . $this->delimiter . ' (0x' . bin2hex($this->delimiter) . ')');
                    }
                }
                if (is_null($this->enclosure)) {
                    if (is_string($guess['enclosure'])) {
                        $this->enclosure = $guess['enclosure'];
                        $this->debug && error_log(__METHOD__ . ' Guessed enclosure: ' . $this->enclosure . ' (' . (strlen($this->enclosure) ? '0x' . bin2hex($this->enclosure) : 'none') . ')');
                    }
                    else {
                        $this->enclosure = '"';
                        $this->debug && error_log(__METHOD__ . ' Default enclosure: ' . $this->enclosure . ' (' . (strlen($this->enclosure) ? '0x' . bin2hex($this->enclosure) : 'none') . ')');
                    }
                }
            }
    
            // Guess file encoding if unknown.
            if (!$this->file_encoding) {
                $encodings = array_unique(array_merge(
                        $this->internal_encoding ? array($this->internal_encoding) : array(),
                        mb_detect_order(),
                        array('UTF-32BE', 'UTF-32LE', 'UTF-16BE', 'UTF-16LE', 'UTF-8', 'Windows-1252', 'cp1252', 'ISO-8859-1') // common file encodings
                ));
                $this->debug && error_log(__METHOD__ . ' Guessing file encoding using encodings: ' . join(', ', $encodings));
                $this->file_encoding = mb_detect_encoding($s, $encodings, true);
                unset($s, $encodings);
                $this->debug && error_log(__METHOD__ . ' Guessed line separator: ' . ' (0x' . bin2hex($this->line_separator) . ')');
            }
    
            // Guess line separator.
            if (!$this->line_separator) {
                if (preg_match('/^UTF-(?:16|32)/', $this->file_encoding)) { // Multibyte line separators. Can't use fgets() here because it doesn't support multibyte line separators.
                    if ($this->file_encoding = 'UTF-16LE') {
                        $this->line_separator = "\x0A\x00";
                    }
                    elseif ($this->file_encoding = 'UTF-16BE') {
                        $this->line_separator = "\x00\x0A";
                    }
                    elseif ($this->file_encoding = 'UTF-32LE') {
                        $this->line_separator = "\x0A\x00\x00\x00";
                    }
                    elseif ($this->file_encoding = 'UTF-32BE') {
                        $this->line_separator = "\x00\x00\x00\x0A";
                    }
                    else {
                        throw new Exception('Line ending detection for file encoding ' . $this->file_encoding . ' not implemented yet.');
                    }
                }
                else {
                    $this->line_separator = "\n";
                }
            }
            $this->debug && error_log(__METHOD__ . ' Guessed line separator: ' . bin2hex($this->line_separator));
        }
    
        // Determine if transcoding is necessary for _fgetcsv().
        if ($this->file_encoding && $this->internal_encoding && strcasecmp($this->file_encoding, $this->internal_encoding)) {
            $this->must_transcode = true;
            // Try to gain effeciency here by eliminating transcoding if file encoding is a subset or alias of internal encoding.
            if ($this->file_encoding == 'ASCII') {
                if (in_array($this->internal_encoding, array('UTF-8', 'Windows-1252', 'cp1252', 'ISO-8859-1'))) {
                    $this->must_transcode = false;
                }
            }
            elseif ($this->file_encoding == 'ISO-8859-1') {
                if (in_array($this->internal_encoding, array('Windows-1252', 'cp1252'))) {
                    $this->must_transcode = false;
                }
            }
            elseif ($this->file_encoding == 'Windows-1252') {
                if ($this->internal_encoding == 'cp1252') { // alias
                    $this->must_transcode = false;
                }
            }
            elseif ($this->file_encoding == 'cp1252') {
                if ($this->internal_encoding == 'Windows-1252') { // alias
                    $this->must_transcode = false;
                }
            }
        }
        $this->debug && error_log(__METHOD__ . ' Must transcode: ' . var_export($this->must_transcode, 1));
    
        // Read header row.
        //@trigger_error('');
        if ($row = $this->_fgetcsv()) {
            $this->debug && error_log(__METHOD__ . ' Raw header row: ' . print_r($row,1));
            $unknown_fieldinfo = array(); # field name => col index pairs
    
            // Get the fieldname => column indices
            $x = 0;
            for ($x = 0; $x < count($row); $x++) {
                $name = trim($row[$x]);
                if (!(is_string($name) && strlen($name))) {
                    continue;
                }
                if ($opt_field_normalizer) {
                    call_user_func_array($opt_field_normalizer, array(&$name));
                    if (!(is_string($name) && strlen($name))) {
                        throw new \InvalidArgumentException("The 'field_normalizer' callback doesn't behave properly because the normalized field name is not a non-empty string");
                    }
                }
                if ($opt_field_aliases) {
                    $alias = mb_strtolower($name);
                    if (array_key_exists($alias, $opt_field_aliases)) {
                        $name = $opt_field_aliases[$alias];
                    }
                }
                if ($opt_include_fields) {
                    if (!in_array($name, $opt_include_fields)) {
                        continue;
                    }
                }
                if (array_key_exists($name, $this->field_cols)) {
                    throw new CSVReaderException('Duplicate field "' . $name . '" detected');
                }
                $this->field_cols[$name] = $x;
            }
            $this->debug && error_log(__METHOD__ . ' Field name => column index pairs: ' . print_r($this->field_cols,1));
        }
    
        // Check that all the required header fields are present.
        if ($opt_include_fields) {
            $missing = array();
            foreach ($opt_include_fields as $name) {
                if (!array_key_exists($name, $this->field_cols)) {
                    array_push($missing, $name);
                }
            }
            if ($missing) {
                throw new CSVReaderException('The following column headers are missing: ' . join(', ', $missing));
            }
        }
    
        // Read first data row.
        $this->_read();
    }
    
    /**
     * Destructor.
     */
    public function __destruct() {
        if ($this->own_h) {
            fclose($this->h);
        }
    }


    // ==============================================
    // ----- [ Main Functions ] ---------------------
    // ==============================================


    /**
     * Parse
     * Parse a CSV file or string
     *
     * @access public
     * @param  [string]  input      The CSV string or a direct filepath
     * @param  [integer] offset     Number of rows to ignore from the beginning of  the data
     * @param  [integer] limit      Limits the number of returned rows to specified amount
     * @param  [string]  conditions Basic SQL-like conditions for row matching
     *
     * @return [bool]
     */
    public function parse ($input = null, $offset = null, $limit = null, $conditions = null) {
        if (is_null($input)) {
            $input = $this->file;
        }

        if ((is_string($input) && !empty($input)) || is_resource($input)) {
            if (!is_null($offset)) {
                $this->offset = $offset;
            }

            if (!is_null($limit)) {
                $this->limit = $limit;
            }

            if (!is_null($conditions)) {
                $this->conditions = $conditions;
            }

            if (is_string($input)) {
                if (is_readable($input)) {
                    $this->own_h = true;
                    $this->data = $this->parse_file($input);
                }
                else {
                    $this->file_data = &$input;
                    $this->data      = $this->parse_string();
                }
            }
            else if (is_resource($input)) {
                $this->own_h = false;
                $this->data = $this->parse_file($input);
            }
            else {
                throw new \InvalidArgumentException(gettype($input) . ' is not a legal file argument type');
            }

            if ($this->data === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Save
     * Save changes, or write a new file and/or data
     *
     * @access public
     * @param  [string] $file   File location to save to
     * @param  [array]  $data   2D array of data
     * @param  [bool]   $append Append current data to end of target CSV, if file exists
     * @param  [array]  $fields Field names
     *
     * @return [bool]
     */
    public function save ($file = null, $data = array(), $append = false, $fields = array()) {
        if (empty($file)) {
            $file = &$this->file;
        }

        $mode   = ($append) ? 'at' : 'wt';
        $is_php = (preg_match('/\.php$/i', $file)) ? true : false;

        return $this->_wfile($file, $this->unparse($data, $fields, $append, $is_php), $mode);
    }

    /**
     * Output
     * Generate a CSV based string for output.
     *
     * @access public
     * @param  [string] $filename  If specified, headers and data will be output directly to browser as a downloable file
     * @param  [array]  $data      2D array with data
     * @param  [array]  $fields    Field names
     * @param  [type]   $delimiter delimiter used to separate data
     *
     * @return [string]
     */
    public function output ($filename = null, $data = array(), $fields = array(), $delimiter = null) {
        if (empty($filename)) {
            $filename = $this->output_filename;
        }

        if ($delimiter === null) {
            $delimiter = $this->output_delimiter;
        }

        $data = $this->unparse($data, $fields, null, null, $delimiter);

        if (!is_null($filename)) {
            header('Content-type: application/csv');
            header('Content-Length: '.strlen($data));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Content-Disposition: attachment; filename="'.$filename.'"; modification-date="'.date('r').'";');

            echo $data;
        }

        return $data;
    }

    /**
     * Encoding
     * Convert character encoding
     *
     * @access public
     * @param  [string] $input  Input character encoding, uses default if left blank
     * @param  [string] $output Output character encoding, uses default if left blank
     */
    public function encoding ($input = null, $output = null) {
        $this->convert_encoding = true;
        if (!is_null($input)) {
            $this->input_encoding = $input;
        }

        if (!is_null($output)) {
            $this->output_encoding = $output;
        }
    }

    /**
     * Auto
     * Auto-Detect Delimiter: Find delimiter by analyzing a specific number of
     * rows to determine most probable delimiter character
     *
     * @access public
     * @param  [string] $file         Local CSV file
     * @param  [bool]   $parse        True/false parse file directly
     * @param  [int]    $search_depth Number of rows to analyze
     * @param  [string] $preferred    Preferred delimiter characters
     * @param  [string] $enclosure    Enclosure character, default is double quote (").
     *
     * @return [string]
     */
    public function auto ($file = null, $parse = true, $search_depth = null, $preferred = null, $enclosure = null) {
        if (is_null($file)) {
            $file = $this->file;
        }

        if (empty($search_depth)) {
            $search_depth = $this->auto_depth;
        }

        if (is_null($enclosure)) {
            $enclosure = $this->enclosure;
        }

        if (is_null($preferred)) {
            $preferred = $this->auto_preferred;
        }

        if (empty($this->file_data)) {
            if ($this->_check_data($file)) {
                $data = &$this->file_data;
            }
            else {
                return false;
            }
        }
        else {
            $data = &$this->file_data;
        }

        $chars    = array();
        $strlen   = strlen($data);
        $enclosed = false;
        $n        = 1;
        $to_end   = true;

        // walk specific depth finding posssible delimiter characters
        for ($i=0; $i < $strlen; $i++) {
            $ch  = $data{$i};
            $nch = (isset($data{$i+1})) ? $data{$i+1} : false ;
            $pch = (isset($data{$i-1})) ? $data{$i-1} : false ;

            // open and closing quotes
            if ($ch == $enclosure) {
                if (!$enclosed || $nch != $enclosure) {
                    $enclosed = ($enclosed) ? false : true ;
                }
                elseif ($enclosed) {
                    $i++;
                }

            // end of row
            }
            elseif (($ch == "\n" && $pch != "\r" || $ch == "\r") && !$enclosed) {
                if ($n >= $search_depth) {
                    $strlen = 0;
                    $to_end = false;
                }
                else {
                    $n++;
                }

            // count character
            }
            elseif (!$enclosed) {
                if (!preg_match('/['.preg_quote($this->auto_non_chars, '/').']/i', $ch)) {
                    if (!isset($chars[$ch][$n])) {
                        $chars[$ch][$n] = 1;
                    }
                    else {
                        $chars[$ch][$n]++;
                    }
                }
            }
        }

        // filtering
        $depth    = ($to_end) ? $n-1 : $n;
        $filtered = array();
        foreach ($chars as $char => $value) {
            if ($match = $this->_check_count($char, $value, $depth, $preferred)) {
                $filtered[$match] = $char;
            }
        }

        // capture most probable delimiter
        ksort($filtered);
        $this->delimiter = reset($filtered);

        // parse data
        if ($parse) {
            $this->data = $this->parse_string();
        }

        return $this->delimiter;
    }


    // ==============================================
    // ----- [ Core Functions ] ---------------------
    // ==============================================

    /**
     * Parse File
     * Read file to string and call parse_string()
     *
     * @access public
     *
     * @param  [string] $file Local CSV file
     *
     * @return [array|bool]
     */
    public function parse_file ($file = null) {
        if (is_null($file)) {
            $file = $this->file;
        }

        if (empty($this->file_data)) {
            $this->load_data($file);
        }

        return (!empty($this->file_data)) ? $this->parse_string() : false;
    }

    /**
     * Parse CSV strings to arrays
     *
     * @access public
     * @param   data   CSV string
     *
     * @return  2D array with CSV data, or false on failure
     */
    public function parse_string ($data = null) {
        if (empty($data)) {
            if ($this->_check_data()) {
                $data = &$this->file_data;
            }
            else {
                return false;
            }
        }

        $white_spaces = str_replace($this->delimiter, '', " \t\x0B\0");

        $rows         = array();
        $row          = array();
        $row_count    = 0;
        $current      = '';
        $head         = (!empty($this->fields)) ? $this->fields : array();
        $col          = 0;
        $enclosed     = false;
        $was_enclosed = false;
        $strlen       = strlen($data);

        // force the parser to process end of data as a character (false) when
        // data does not end with a line feed or carriage return character.
        $lch = $data{$strlen-1};
        if ($lch != "\n" && $lch != "\r") {
            $strlen++;
        }

        // walk through each character
        for ($i=0; $i < $strlen; $i++) {
            $ch  = (isset($data{$i}))   ? $data{$i}   : false;
            $nch = (isset($data{$i+1})) ? $data{$i+1} : false;
            $pch = (isset($data{$i-1})) ? $data{$i-1} : false;

            // open/close quotes, and inline quotes
            if ($ch == $this->enclosure) {
                if (!$enclosed) {
                    if (ltrim($current,$white_spaces) == '') {
                        $enclosed     = true;
                        $was_enclosed = true;
                    }
                    else {
                        $this->error = 2;
                        $error_row   = count($rows) + 1;
                        $error_col   = $col + 1;
                        if (!isset($this->error_info[$error_row.'-'.$error_col])) {
                            $this->error_info[$error_row.'-'.$error_col] = array(
                                'type'       => 2,
                                'info'       => 'Syntax error found on row '.$error_row.'. Non-enclosed fields can not contain double-quotes.',
                                'row'        => $error_row,
                                'field'      => $error_col,
                                'field_name' => (!empty($head[$col])) ? $head[$col] : null,
                            );
                        }

                        $current .= $ch;
                    }
                }
                elseif ($nch == $this->enclosure) {
                    $current .= $ch;
                    $i++;
                }
                elseif ($nch != $this->delimiter && $nch != "\r" && $nch != "\n") {
                    for ($x=($i+1); isset($data{$x}) && ltrim($data{$x}, $white_spaces) == ''; $x++) {}
                    if ($data{$x} == $this->delimiter) {
                        $enclosed = false;
                        $i        = $x;
                    }
                    else {
                        if ($this->error < 1) {
                            $this->error = 1;
                        }

                        $error_row = count($rows) + 1;
                        $error_col = $col + 1;
                        if (!isset($this->error_info[$error_row.'-'.$error_col])) {
                            $this->error_info[$error_row.'-'.$error_col] = array(
                                'type' => 1,
                                'info' =>
                                    'Syntax error found on row '.(count($rows) + 1).'. '.
                                    'A single double-quote was found within an enclosed string. '.
                                    'Enclosed double-quotes must be escaped with a second double-quote.',
                                'row'        => count($rows) + 1,
                                'field'      => $col + 1,
                                'field_name' => (!empty($head[$col])) ? $head[$col] : null,
                            );
                        }

                        $current .= $ch;
                        $enclosed = false;
                    }
                }
                else {
                    $enclosed = false;
                }

            // end of field/row/csv
            }
            elseif ( ($ch == $this->delimiter || $ch == "\n" || $ch == "\r" || $ch === false) && !$enclosed ) {
                $key           = (!empty($head[$col])) ? $head[$col] : $col;
                $row[$key]     = ($was_enclosed) ? $current : trim($current);
                $current       = '';
                $was_enclosed  = false;
                $col++;

                // end of row
                if ($ch == "\n" || $ch == "\r" || $ch === false) {
                    if ($this->_validate_offset($row_count) && $this->_validate_row_conditions($row, $this->conditions)) {
                        if ($this->heading && empty($head)) {
                            $head = $row;
                        }
                        elseif (empty($this->fields) || (!empty($this->fields) && (($this->heading && $row_count > 0) || !$this->heading))) {
                            if (!empty($this->sort_by) && !empty($row[$this->sort_by])) {
                                if (isset($rows[$row[$this->sort_by]])) {
                                    $rows[$row[$this->sort_by].'_0'] = &$rows[$row[$this->sort_by]];
                                    unset($rows[$row[$this->sort_by]]);
                                    for ($sn=1; isset($rows[$row[$this->sort_by].'_'.$sn]); $sn++) {}
                                    $rows[$row[$this->sort_by].'_'.$sn] = $row;
                                }
                                else $rows[$row[$this->sort_by]] = $row;
                            }
                            else {
                                $rows[] = $row;
                            }
                        }
                    }

                    $row = array();
                    $col = 0;
                    $row_count++;

                    if ($this->sort_by === null && $this->limit !== null && count($rows) == $this->limit) {
                        $i = $strlen;
                    }

                    if ($ch == "\r" && $nch == "\n") {
                        $i++;
                    }
                }

            // append character to current field
            }
            else {
                $current .= $ch;
            }
        }

        $this->titles = $head;
        if (!empty($this->sort_by)) {
            $sort_type = SORT_REGULAR;
            if ($this->sort_type == 'numeric') {
                $sort_type = SORT_NUMERIC;
            }
            elseif ($this->sort_type == 'string') {
                $sort_type = SORT_STRING;
            }

            ($this->sort_reverse) ? krsort($rows, $sort_type) : ksort($rows, $sort_type);

            if ($this->offset !== null || $this->limit !== null) {
                $rows = array_slice($rows, ($this->offset === null ? 0 : $this->offset) , $this->limit, true);
            }
        }

        if (!$this->keep_file_data) {
            $this->file_data = null;
        }

        return $rows;
    }

    /**
     * Create CSV data from array
     *
     * @access public
     * @param   data        2D array with data
     * @param   fields      field names
     * @param   append      if true, field names will not be output
     * @param   is_php      if a php die() call should be put on the first
     *                      line of the file, this is later ignored when read.
     * @param   delimiter   field delimiter to use
     *
     * @return  CSV data (text string)
     */
    public function unparse ($data = array(), $fields = array(), $append = false , $is_php = false, $delimiter = null) {
        if (!is_array($data) || empty($data)) {
            $data = &$this->data;
        }

        if (!is_array($fields) || empty($fields))  {
            $fields = &$this->titles;
        }

        if ($delimiter === null) {
            $delimiter = $this->delimiter;
        }

        $string = ($is_php) ? "<?php header('Status: 403'); die(' '); ?>".$this->linefeed : '';
        $entry  = array();

        // create heading
        if ($this->heading && !$append && !empty($fields)) {
            foreach ($fields as $key => $value) {
                $entry[] = $this->_enclose_value($value, $delimiter);
            }

            $string .= implode($delimiter, $entry).$this->linefeed;
            $entry   = array();
        }

        // create data
        foreach ($data as $key => $row) {
            foreach ($row as $field => $value) {
                $entry[] = $this->_enclose_value($value, $delimiter);
            }

            $string .= implode($delimiter, $entry).$this->linefeed;
            $entry   = array();
        }

        return $string;
    }

    /**
     * Load local file or string
     *
     * @access public
     * @param   input   local CSV file
     *
     * @return  true or false
     */
    public function load_data ($input = null) {
        $data = null;
        $file = null;

        if (is_null($input)) {
            $file = $this->file;
        }
        elseif (file_exists($input)) {
            $file = $input;
        }
        else if (is_resource($input)) {
            $file = $input;
        }
        else {
            $data = $input;
        }

        if (!empty($data) || $data = $this->_rfile($file)) {
            if ($this->file != $file) {
                $this->file = $file;
            }

            if (preg_match('/\.php$/i', $file) && preg_match('/<\?.*?\?>(.*)/ims', $data, $strip)) {
                $data = ltrim($strip[1]);
            }

            if ($this->convert_encoding) {
                $data = iconv($this->input_encoding, $this->output_encoding, $data);
            }

            if (substr($data, -1) != "\n") {
                $data .= "\n";
            }

            $this->file_data = &$data;
            return true;
        }

        return false;
    }


    // ==============================================
    // ----- [ Internal Functions ] -----------------
    // ==============================================

    /**
     * Validate a row against specified conditions
     *
     * @access protected
     * @param   row          array with values from a row
     * @param   conditions   specified conditions that the row must match
     *
     * @return  true of false
     */
    protected function _validate_row_conditions ($row = array(), $conditions = null) {
        if (!empty($row)) {
            if (!empty($conditions)) {
                $conditions = (strpos($conditions, ' OR ') !== false) ? explode(' OR ', $conditions) : array($conditions);
                $or = '';
                foreach ($conditions as $key => $value) {
                    if (strpos($value, ' AND ') !== false) {
                        $value = explode(' AND ', $value);
                        $and   = '';

                        foreach ($value as $k => $v) {
                            $and .= $this->_validate_row_condition($row, $v);
                        }

                        $or .= (strpos($and, '0') !== false) ? '0' : '1';
                    }
                    else {
                        $or .= $this->_validate_row_condition($row, $value);
                    }
                }

                return (strpos($or, '1') !== false) ? true : false;
            }

            return true;
        }

        return false;
    }

    /**
     * Validate a row against a single condition
     *
     * @access protected
     * @param   row          array with values from a row
     * @param   condition   specified condition that the row must match
     *
     * @return  true of false
     */
    protected function _validate_row_condition ($row, $condition) {
        $operators = array(
            '=', 'equals', 'is',
            '!=', 'is not',
            '<', 'is less than',
            '>', 'is greater than',
            '<=', 'is less than or equals',
            '>=', 'is greater than or equals',
            'contains',
            'does not contain',
        );

        $operators_regex = array();

        foreach ($operators as $value) {
            $operators_regex[] = preg_quote($value, '/');
        }

        $operators_regex = implode('|', $operators_regex);

        if (preg_match('/^(.+) ('.$operators_regex.') (.+)$/i', trim($condition), $capture)) {
            $field = $capture[1];
            $op    = $capture[2];
            $value = $capture[3];

            if (preg_match('/^([\'\"]{1})(.*)([\'\"]{1})$/i', $value, $capture)) {
                if ($capture[1] == $capture[3]) {
                    $value = $capture[2];
                    $value = str_replace("\\n", "\n", $value);
                    $value = str_replace("\\r", "\r", $value);
                    $value = str_replace("\\t", "\t", $value);
                    $value = stripslashes($value);
                }
            }

            if (array_key_exists($field, $row)) {
                if (($op == '=' || $op == 'equals' || $op == 'is') && $row[$field] == $value) {
                    return '1';
                }
                elseif (($op == '!=' || $op == 'is not') && $row[$field] != $value) {
                    return '1';
                }
                elseif (($op == '<' || $op == 'is less than' ) && $row[$field] < $value) {
                    return '1';
                }
                elseif (($op == '>' || $op == 'is greater than') && $row[$field] > $value) {
                    return '1';
                }
                elseif (($op == '<=' || $op == 'is less than or equals' ) && $row[$field] <= $value) {
                    return '1';
                }
                elseif (($op == '>=' || $op == 'is greater than or equals') && $row[$field] >= $value) {
                    return '1';
                }
                elseif ($op == 'contains' && preg_match('/'.preg_quote($value, '/').'/i', $row[$field])) {
                    return '1';
                }
                elseif ($op == 'does not contain' && !preg_match('/'.preg_quote($value, '/').'/i', $row[$field])) {
                    return '1';
                }
                else {
                    return '0';
                }
            }
        }

        return '1';
    }

    /**
     * Validates if the row is within the offset or not if sorting is disabled
     *
     * @access protected
     * @param   current_row   the current row number being processed
     *
     * @return  true of false
     */
    protected function _validate_offset ($current_row) {
        if ($this->sort_by === null && $this->offset !== null && $current_row < $this->offset) {
            return false;
        }

        return true;
    }

    /**
     * Enclose values if needed
     *  - only used by unparse()
     *
     * @access protected
     * @param  value   string to process
     *
     * @return Processed value
     */
    protected function _enclose_value ($value = null, $delimiter = null) {
        if (is_null($delimiter)) {
            $delimiter = $this->delimiter;
        }
        if ($value !== null && $value != '') {
            $delimiter_quoted = preg_quote($delimiter, '/');
            $enclosure_quoted = preg_quote($this->enclosure, '/');
            if (preg_match("/".$delimiter_quoted."|".$enclosure_quoted."|\n|\r/i", $value) || ($value{0} == ' ' || substr($value, -1) == ' ') || $this->enclose_all) {
                $value = str_replace($this->enclosure, $this->enclosure.$this->enclosure, $value);
                $value = $this->enclosure.$value.$this->enclosure;
            }
        }

        return $value;
    }

    /**
     * Check file data
     *
     * @access protected
     * @param   file   local filename
     *
     * @return  true or false
     */
    protected function _check_data ($file = null) {
        if (empty($this->file_data)) {
            if (is_null($file)) $file = $this->file;

            return $this->load_data($file);
        }

        return true;
    }

    /**
     * Check if passed info might be delimiter
     * Only used by find_delimiter
     *
     * @access protected
     * @param  [type] $char      [description]
     * @param  [type] $array     [description]
     * @param  [type] $depth     [description]
     * @param  [type] $preferred [description]
     *
     * @return special string used for delimiter selection, or false
     */
    protected function _check_count ($char, $array, $depth, $preferred) {
        if ($depth == count($array)) {
            $first  = null;
            $equal  = null;
            $almost = false;
            foreach ($array as $key => $value) {
                if ($first == null) {
                    $first = $value;
                }
                elseif ($value == $first && $equal !== false) {
                    $equal = true;
                }
                elseif ($value == $first+1 && $equal !== false) {
                    $equal  = true;
                    $almost = true;
                }
                else {
                    $equal = false;
                }
            }

            if ($equal) {
                $match = ($almost) ? 2 : 1;
                $pref  = strpos($preferred, $char);
                $pref  = ($pref !== false) ? str_pad($pref, 3, '0', STR_PAD_LEFT) : '999';

                return $pref.$match.'.'.(99999 - str_pad($first, 5, '0', STR_PAD_LEFT));
            }
            else {
                return false;
            }
        }
    }

    /**
     * Read local file
     *
     * @access protected
     * @param   file   local filename
     *
     * @return  Data from file, or false on failure
     */
    protected function _rfile ($file = null) {
        if (is_readable($file)) {
            if (!($this->h = fopen($file, 'r'))) {
                return false;
            }

            $data = fread($this->h, filesize($file));
            fclose($this->h);
            return $data;
        }
        else if (is_resource($file)) {
            $this->h = $file;
            $data = fread($this->h, filesize($file));
            return $data;
        }

        return false;
    }

    /**
     * Write to local file
     *
     * @access protected
     * @param   file     local filename
     * @param   string   data to write to file
     * @param   mode     fopen() mode
     * @param   lock     flock() mode
     *
     * @return  true or false
     */
    protected function _wfile ($file, $string = '', $mode = 'wb', $lock = 2) {
        if ($fp = fopen($file, $mode)) {
            flock($fp, $lock);
            $re  = fwrite($fp, $string);
            $re2 = fclose($fp);
            if ($re != false && $re2 != false)  {
                return true;
            }
        }

        return false;
    }
    
    //----------------------------------------------------------------------------
    //  CSVReader functions
    //----------------------------------------------------------------------------
    
    /**
     * Peeks into a string of CSV data and tries to guess the delimiter, enclosure, and line separator.
     * Returns an associative array with keys 'line_separator', 'delimiter', 'enclosure'.
     * Undetectable values will be null.
     *
     * @param string $data any length of data, but preferrably long enough to contain at least one whole line.
     * @param string $data_encoding optional
     * @param string $line_separator optional
     * @return array
     */
    public static function csv_guess($data, $data_encoding = null, $line_separator = null) {
        // TODO: see Perl's Text::CSV::Separator which uses a more advanced approach to detect the delimiter.
        $result = array(
                'line_separator' => null,
                'delimiter'	=> null,
                'enclosure'	=> null,
        );
        $delimiters = array(',', ';', ':', '|', "\t");
        $enclosures = array('"', "'", '');
        $multibyte = $data_encoding && preg_match('/^UTF-(?:16|32)/', $data_encoding);
        if ($multibyte) { // damn multibyte characters
            foreach ($delimiters as &$x) {
                $x = iconv('latin1', $data_encoding, $x);
                unset($x);
            }
            foreach ($enclosures as &$x) {
                $x = iconv('latin1', $data_encoding, $x);
                unset($x);
            }
        }
        // Scan the 1st line only:
        $line = null;
        $cr = "\r";
        $lf = "\n";
        if ($multibyte) {
            $cr = iconv('latin1', $data_encoding, $cr);
            $lf = iconv('latin1', $data_encoding, $lf);
        }
        if (preg_match('/^(.*?)(' . preg_quote("$lf$cr") . '|' . preg_quote("$lf") . '(?!' . preg_quote("$cr") . ')|' . preg_quote("$cr") . '(?!' . preg_quote("$lf") . ')|' . preg_quote("$cr$lf") . ')/', $data, $matches)) {
            $line = $matches[1];
            // Guess line separator
            if (isset($matches[2]) && strlen($matches[2])) {
                $result['line_separator'] = $matches[2];
            }
    
            // Guess delimiter:
            if (1) {
                $max_count = 0;
                $guessed_delimiter = null;
                foreach ($delimiters as $delimiter) {
                    $count = substr_count($line, $delimiter);
                    if ($count > $max_count) {
                        $max_count = $count;
                        $guessed_delimiter = $delimiter;
                    }
                }
                $result['delimiter'] = $guessed_delimiter;
            }
    
            // Guess enclosure
            if ($result['delimiter']) {
                $max_count = 0;
                $guessed_enclosure = null;
                foreach ($enclosures as $enclosure) {
                    $count = substr_count($line, $enclosure . $result['delimiter'] . $enclosure);
                    if ($count > $max_count) {
                        $max_count = $count;
                        $guessed_enclosure = $enclosure;
                    }
                }
                $result['enclosure'] = $guessed_enclosure;
            }
        }
        return $result;
    }
    
    
    /**
     * Reads a CSV line, parses it into an array using str_getcsv(), and performs transcoding if necessary.
     *
     * @param boolean $iconv_strict
     * @return array|false
     */
    protected function _fgetcsv($iconv_strict = false) {
        $line = null;
        if (!$this->seekable && ($this->initial_lines_index < count($this->initial_lines))) {
            $line = $this->initial_lines[$this->initial_lines_index++];
            if ($this->initial_lines_index >= count($this->initial_lines)) {
                $this->initial_lines = array(); // no longer needed, free memory
            }
            //$this->debug && error_log(__METHOD__ . ' got line from initial_lines: ' . bin2hex($line));
        }
        else {
            // Not using fgetcsv() because it is dependent on the locale setting.
            $line = stream_get_line($this->h, $this->length, $this->line_separator);
            //if (($line === false) || feof($this->h)) { // feof() check is needed for PHP < 5.4.4 because stream_get_line() kept returning an empty string instead of false at eof.
            if (($line === false)) { // testing on 2014-06-17 with PHP 5.4.12, the feof()-fix results in the last row of a csv being ignored hence not parsed - without it seems to be working fine so far
                return false;
            }
            //$this->debug && error_log(__METHOD__ . ' read line: ' . bin2hex($line));
        }
        if (!strlen($line)) {
            return array();
        }
        if ($this->must_transcode) {
            //$this->debug && error_log(__METHOD__ . ' transcode string ' . bin2hex($line));
            $to_encoding = $this->internal_encoding;
            if (!$iconv_strict) {
                $to_encoding .= '//IGNORE//TRANSLIT';
            }
            $line = iconv($this->file_encoding, $to_encoding, $line);
            if ($line === false) { // iconv failed
                return false;
            }
        }
        $csv = str_getcsv($line, $this->delimiter, $this->enclosure, $this->escape);
        // TODO: nicht str_getcsv() verwenden sondern $this->parse_string() oder ähnliche Funktion erschaffen
        if (!$csv || (is_null($csv[0]) && (count($csv) == 1))) {
            return array();
        }
        return $csv;
    }
    
    
    /**
     * Reads the next CSV data row and sets internal variables.
     * Returns false if EOF was reached, else true.
     * Skips empty lines if option 'skip_empty_lines' is true.
     *
     * @return boolean
     */
    protected function _read() {
        while (($row = $this->_fgetcsv()) !== false) {
            if ($row) {
                foreach ($row as &$col) {
                    if (!is_null($col)) {
                        $col = trim($col);
                        if (!strlen($col)) {
                            $col = null;
                        }
                    }
                }
                $class = $this->_rowClassName();
                $this->row = new $class($row, $this->field_cols);
                $this->key++;
            }
            else { // blank line
                $this->row = null;
                $this->key++;
                if ($this->skip_empty_lines) {
                    continue;
                }
            }
            break;
        }
        if ($row === false) {
            $this->row = null;
            $this->key = -1;
            $this->initial_lines_index = 0;
            return false;
        }
    }
    
    /**
     * Returns the row class name 'CSVReaderRow'.
     * You may override this method to return a custom row class name.
     *
     * @return string
     */
    protected function _rowClassName() {
        return 'CSVReaderRow'; // or __CLASS__ . 'Row'
    }
    
    
    /**
     * Returns the field names.
     *
     * @return array
     */
    public function fieldNames() {
        return array_keys($this->field_cols);
    }
    
    
    /**
     * Required Iterator interface method.
     */
    public function current() {
        if ($this->read_mode == 'chunked') {
            return $this->row;
        }
        else {
            return $this->data[$this->position];
        }
    }
    
    
    /**
     * Required Iterator interface method.
     */
    public function key() {
        if ($this->read_mode == 'chunked') {
            return $this->key;
        }
        else {
            return $this->position;
        }
    }
    
    
    /**
     * Required Iterator interface method.
     */
    public function next() {
        if ($this->read_mode == 'chunked') {
            $this->_read();
        }
        else {
            ++$this->position;
        }
    }
    
    
    /**
     * Required Iterator interface method.
     */
    public function rewind() {
        if ($this->read_mode == 'chunked') {
            if (!$this->seekable) { // rewind() is called whenever a foreach loop is started.
                return; // Just return without a warning/error.
            }
            if (fseek($this->h, $this->bom_len) != 0) {
                throw new \Exception('Failed to fseek to ' . $this->bom_len);
            }
            $this->row = null;
            $this->key = -1;
            $this->initial_lines_index == 0;
            if ($this->_fgetcsv()) { // skip header row
                $this->_read();
            }
        }
        else {
            $this->position = 0;
        }
    }
    
    
    /**
     * Determines if the stream is seekable.
     */
    public function seekable() {
        return $this->seekable;
    }
    
    
    /**
     * Required Iterator interface method.
     */
    public function valid() {
        if ($this->read_mode == 'chunked') {
            return !is_null($this->row);
        }
        else {
            return isset($this->data[$this->position]);
        }
    }
}



/**
 * Encapsulates a CSV row.
 * Created and returned by parseCSV.
 * This object should consume less memory than an associative array.
 */
class CSVReaderRow implements \Countable, \IteratorAggregate, \ArrayAccess {

    protected $field_cols;
    protected $row;
    protected $pairs; // cached result of toArray()


    /**
     * Constructor.
     *
     * @param array $row
     * @param array $field_cols associative array of fieldname => column index pairs.
     */
    public function __construct(array $row, array $field_cols) {
        $this->row			= $row;
        $this->field_cols	= $field_cols; // reference counted, not a copy
    }


    /**
     * Returns the value of the field having the given column name.
     *
     * @param string $name
     * @return string|null
     */
    public function get($name) {
        if (!array_key_exists($name, $this->field_cols)) {
            return null;
        }
        $i = $this->field_cols[$name];
        if ($i >= count($this->row)) {
            return null;
        }
        return $this->row[$i];
    }


    /**
     * Returns the row as an associative array.
     *
     * @param boolean $cacheable default false
     * @return array
     */
    public function toArray($cacheable = false) {
        $pairs = $this->pairs;
        if (is_null($pairs)) {
            $pairs = array();
            foreach ($this->field_cols as $name => $i) {
                $i = $this->field_cols[$name];
                if ($i >= count($this->row)) {
                    continue;
                }
                $pairs[$name] = $this->row[$i];
            }
            if ($cacheable) {
                $this->pairs = $pairs;
            }
        }
        return $pairs;
    }


    /**
     * Return count of items in collection.
     * Implements countable
     *
     * @return integer
     */
    public function count() {
        return count($this->toArray());
    }


    /**
     * Returns the keys because array_keys() can't (yet).
     *
     * @return array
     */
    public function keys() {
        return array_keys($this->toArray());
    }


    /**
     * Implements IteratorAggregate
     *
     * @return ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->toArray());
    }


    /**
     * Implements ArrayAccess
     */
    public function offsetSet($offset, $value) {
        // Ignored because this is readonly
    }


    /**
     * Implements ArrayAccess
     *
     * @return boolean
     */
    public function offsetExists($offset) {
        return array_key_exists($offset, $this->toArray());
    }


    /**
     * Implements ArrayAccess
     */
    public function offsetUnset($offset) {
        unset($this->pairs[$offset]);
    }


    /**
     * Implements ArrayAccess
     *
     * @return boolean
     */
    public function offsetGet($offset) {
        $array = $this->toArray();
        return @$array[$offset];
    }
}
