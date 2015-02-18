<?php

namespace Geekwright\Po;

use Geekwright\Po\Exceptions\UnrecognizedInputException;
use Geekwright\Po\Exceptions\FileNotReadableException;
use Geekwright\Po\Exceptions\FileNotWriteableException;

/**
 * PoFile - represent all entries in a GNU gettext style PO or POT file as a
 * collection of PoHeader and PoEntry objects.
 *
 * @category  Po
 * @package   Po\PoFile
 * @author    Richard Griffith <richard@geekwright.com>
 * @copyright 2015 Richard Griffith
 * @license   GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://github.com/geekwright/Po
 */
class PoFile
{
    /**
     * @var PoHeader $header
     */
    protected $header = null;

    /**
     * @var PoEntry[] $entries
     */
    protected $entries = array();

    /**
     * @var PoEntry[] $unkeyedEntries
     */
    protected $unkeyedEntries = array();

    /**
     * $var array() $unrecognizedInput
     *
     * If any lines that cannot be processed are found when reading a po file, the
     * unrecognized input will be recorded here, and an exception will be thrown.
     * No interface is supplied, but this debug data is an array in the form:
     * line number => input line
     */
    public $unrecognizedInput = array();

    /**
     * __construct
     *
     * @param PoHeader|null $header         header object
     * @param PoEntry[]     $entries        associative array po entries
     * @param PoEntry[]     $unkeyedEntries indexed array of po entries
     */
    public function __construct($header = null, $entries = array(), $unkeyedEntries = array())
    {
        $this->header = $header;
        $this->entries = $entries;
        $this->unkeyedEntries = $unkeyedEntries;
    }

    /**
     * createKey - build the internal entries array key from id, context and plural id
     * @param string $msgid        the untranslated message of the entry
     * @param string $msgctxt      the context of the entry, if any
     * @param string $msgid_plural the untranslated plural message of the entry, if any
     * @return string
     */
    public static function createKey($msgid, $msgctxt = null, $msgid_plural = null)
    {
        $key = '';
        if (!empty($msgctxt)) {
            $key .= $msgctxt . '|';
        }
        $key .= $msgid;
        if (!empty($msgid_plural)) {
            $key .= '|' . $msgid_plural;
        }
        return $key;
    }

    /**
     * createKeyFromEntry - build the internal entries array key from a PoEntry
     * @param PoEntry $entry the PoEntry to build key for
     * @return string
     */
    public function createKeyFromEntry(PoEntry $entry)
    {
        return $this->createKey(
            $entry->getAsString(PoTokens::MESSAGE),
            $entry->getAsString(PoTokens::CONTEXT),
            $entry->getAsString(PoTokens::PLURAL)
        );
    }

    /**
     * setHeaderEntry replace any existing header with new PoHeader
     *
     * @param PoHeader $header header object
     * @return void
     */
    public function setHeaderEntry(PoHeader $header)
    {
        $this->header = $header;
    }

    /**
     * getHeaderEntry get current PoHeader object
     *
     * @return PoHeader
     */
    public function getHeaderEntry()
    {
        return $this->header;
    }

    /**
     * getEntries get current array of PoEntry objects
     *
     * @return PoEntry[]
     */
    public function getEntries()
    {
        return $this->entries;
    }

    /**
     * setUnkeyedEntries replace any existing unkeyedEntries with new array of PoEntry objects
     *
     * @param PoEntry[] $entries po entries
     * @return void
     */
    public function setUnkeyedEntries($entries)
    {
        $this->unkeyedEntries = $entries;
    }

    /**
     * getUnkeyedEntries get current array of unkeyed PoEntry objects
     *
     * @return PoEntry[]
     */
    public function getUnkeyedEntries()
    {
        return $this->unkeyedEntries;
    }

    /**
     * addEntry - add an entry to the PoFile using internal key
     * @param PoEntry $entry   the PoEntry to add
     * @param boolean $replace true to replace any existing entry matching this key,
     *                         false to not add if duplicate
     * @return boolean true if added, false if not
     */
    public function addEntry(PoEntry $entry, $replace = true)
    {
        $key = $this->createKeyFromEntry($entry);

        // some entires, such as obsolete entries, have no key
        // for some uses, these are dead weight - need better strategy for that case
        if (empty($key)) {
            $this->unkeyedEntries[] = $entry;
            return true;
        }

        if (isset($this->entries[$key]) && !$replace) {
            return false;
        } else {
            $this->entries[$key] = $entry;
            return true;
        }
    }

    /**
     * mergeEntry - merge an entry with any existing entry with the same key.
     * If the key does not exist, add the entry, otherwise merge comments, references,
     * and flags.
     *
     * This is intended for use in building a POT, where the handling of translated
     * strings is not a factor.
     *
     * @param PoEntry $newEntry the PoEntry to merge
     * @return boolean true if merged or added, false if not
     */
    public function mergeEntry(PoEntry $newEntry)
    {
        $key = $this->createKeyFromEntry($newEntry);

        // keyed entries only
        if (empty($key)) {
            return false;
        }

        if (isset($this->entries[$key])) {
            $existingEntry = $this->entries[$key];
            $mergeTokens = array(PoTokens::REFERENCE, PoTokens::EXTRACTED_COMMENTS);
            foreach ($mergeTokens as $type) {
                $toMerge = $newEntry->get($type);
                if (!empty($toMerge)) {
                    $toMerge = is_array($toMerge) ? $toMerge : array($toMerge);
                    foreach ($toMerge as $value) {
                        $existingEntry->add($type, $value);
                    }
                }
            }
        } else {
            $this->entries[$key] = $newEntry;
        }
        return true;
    }

    /**
     * findEntry - get entry based on key - msgid, msgctxt and msgid_plural
     *
     * @param string $msgid        the untranslated message of the entry
     * @param string $msgctxt      the context of the entry, if any
     * @param string $msgid_plural the untranslated plural message of the entry, if any
     * @return PoEntry|null matching entry, or null if not found
     */
    public function findEntry($msgid, $msgctxt = null, $msgid_plural = null)
    {
        $key = $this->createKey($msgid, $msgctxt, $msgid_plural);
        $entry = null;

        if (!empty($key) && isset($this->entries[$key])) {
            $entry = $this->entries[$key];
        }

        return $entry;
    }

    /**
     * removeEntry - remove an entry from the PoFile
     *
     * In simple cases, the entry can be found by key. There are several cases
     * where it is not that easy to locate the PoEntry to be removed:
     *  - the PoEntry was altered, making the generated and stored key different
     *  - the entry is not keyed and is in unkeyedEntries
     *
     * In any of these cases, we must loop thru the entry arrays looking for an
     * exact object match, so the cost of the remove goes up
     *
     * @param PoEntry $entry the PoEntry to merge
     * @return boolean true if remove, false if not
     */
    public function removeEntry(PoEntry $entry)
    {
        $key = $this->createKeyFromEntry($entry);

        // try by the key first.
        if (!empty($key) && isset($this->entries[$key])) {
            if ($entry === $this->entries[$key]) {
                unset($this->entries[$key]);
                return true;
            }
        }

        // the entry can't be matched by key, so we have to loop :(
        foreach ($this->entries as $key => $value) {
            if ($entry === $value) {
                unset($this->entries[$key]);
                return true;
            }
        }

        // no match found in main entries, try the unkeyedEntries
        foreach ($this->unkeyedEntries as $key => $value) {
            if ($entry === $value) {
                unset($this->unkeyedEntries[$key]);
                return true;
            }
        }

        return false;
    }

    /**
     * writePoFile write any current contents to a po file
     *
     * @param string $file po file to write
     * @return void
     * @throws FileNotWriteableException
     */
    public function writePoFile($file)
    {
        $source = $this->dumpString();
        if (false===file_put_contents($file, $source)) {
            throw new FileNotWriteableException($file);
        }
    }

    /**
     * dump po to string
     * @return string
     */
    public function dumpString()
    {
        if ($this->header === null) {
            $this->header = new PoHeader;
            $this->header->buildDefaultHeader();
        }
        $output = '';

        $output .= $this->header->dumpEntry();
        foreach ($this->entries as $entry) {
            $output .= $entry->dumpEntry();
        }
        foreach ($this->unkeyedEntries as $entry) {
            $output .= $entry->dumpEntry();
        }
        $output .= "\n";

        return $output;
    }


    /**
     * readPoFile replace any current contents with entries from file
     *
     * @param string   $file    po file/stream to read
     * @param resource $context context for stream if required
     * @return void
     * @throws FileNotReadableException
     */
    public function readPoFile($file, resource $context = null)
    {
        $source = file_get_contents($file, false, $context);
        if (false===$source) {
            throw new FileNotReadableException($file);
        }
        $this->parsePoSource($source);
    }

    /**
     * parsePoSource replace any current contents with header and entries from file
     *
     * @param string $source po formatted string to parse
     * @return void
     * @throws UnrecognizedInputException
     */
    public function parsePoSource($source)
    {
        /**
         * This is an incredibly ugly regex pattern that breaks a line of a po file into
         * pieces that can be analyzed and acted upon.
         *
         * The matches array in preg_match will break out like this:
         *  [0] full string
         *  [1] mostly useless broad match of initial token, including trailing space
         *  [2] bare token, or full msgstr[n] clause
         *  [3] 'n' of a msgstr[n] line
         *  [4] '"' if a data line
         *  [5] remaining line
         *  [6] a bare or malformed comment
         */
        $pattern = '/(^(#|#.|#;|#,|#\||msgid|msgid_plural|msgctxt|msgstr|msgstr\[([0-9]+)\])\s|(^"))(.+)|(^#.*)/';

        $source_lines = explode("\n", $source);

        $wsBreak = false;
        $inHeader = true;
        $entry = new PoHeader;
        $unrecognized = array();
        $lastKey = '';
        $currentKey = '';
        foreach ($source_lines as $line => $s) {
            $result = preg_match($pattern, $s, $matches);
            if (!$result) {
                $lastKey = '';
                if ($s=='' || ctype_space($s)) {
                    if ($inHeader) {
                        $this->setHeaderEntry($entry);
                        $entry = null;
                        $inHeader = false;
                    }
                    if (!$wsBreak) {
                        if ($entry === null) {
                            $this->addEntry($entry);
                        }
                        $entry = null;
                        $wsBreak=true;
                    }
                } else {
                    $wsBreak=false;
                    $unrecognized[$line+1] = $s;
                }
            } else {
                if ($entry === null) {
                    $entry = new PoEntry;
                }
                $wsBreak=false;
                $currentKey = $matches[2];  // will be used to set last key
                switch ($matches[2]) {
                    case PoTokens::TRANSLATOR_COMMENTS:
                        $entry->add(PoTokens::TRANSLATOR_COMMENTS, $matches[5]);
                        break;
                    case PoTokens::EXTRACTED_COMMENTS:
                        $entry->add(PoTokens::EXTRACTED_COMMENTS, $matches[5]);
                        break;
                    case PoTokens::REFERENCE:
                        $entry->add(PoTokens::REFERENCE, $matches[5]);
                        break;
                    case PoTokens::FLAG:
                        $entry->add(PoTokens::FLAG, $matches[5]);
                        break;
                    case PoTokens::OBSOLETE:
                        $entry->add(PoTokens::OBSOLETE, $matches[5]);
                        break;
                    case PoTokens::CONTEXT:
                        $entry->addQuoted(PoTokens::CONTEXT, $matches[5]);
                        break;
                    case PoTokens::MESSAGE:
                        $entry->addQuoted(PoTokens::MESSAGE, $matches[5]);
                        break;
                    case PoTokens::PLURAL:
                        $entry->addQuoted(PoTokens::PLURAL, $matches[5]);
                        break;
                    case PoTokens::TRANSLATED:
                        $entry->addQuoted(PoTokens::TRANSLATED, $matches[5]);
                        break;
                    case PoTokens::PREVIOUS:
                        $entry->add(PoTokens::PREVIOUS, $matches[5]);
                        break;
                    default:
                        if ($matches[4]==PoTokens::CONTINUED_DATA) {
                            $currentKey = $lastKey; // keep the previous key
                            if ($currentKey==PoTokens::TRANSLATED_PLURAL) {
                                $entry->addQuotedAtPosition(
                                    PoTokens::TRANSLATED,
                                    '"' . $matches[5],
                                    $currentPlural
                                );
                            } else {
                                $entry->addQuoted($currentKey, '"' . $matches[5]);
                            }
                        } elseif (substr($matches[2], 0, 7)==PoTokens::TRANSLATED_PLURAL) {
                            $currentKey = PoTokens::TRANSLATED_PLURAL;
                            $currentPlural = $matches[3];
                            $entry->addQuotedAtPosition(
                                PoTokens::TRANSLATED,
                                $matches[5],
                                $currentPlural
                            );
                        } elseif (isset($matches[6][0])
                            && $matches[6][0]==PoTokens::TRANSLATOR_COMMENTS) {
                            $value = substr($matches[6], 1);
                            $value = empty($value) ? '' : $value;
                            $entry->add(PoTokens::TRANSLATOR_COMMENTS, $value);
                        } else {
                            $unrecognized[$line+1] = $s;
                        }
                        break;
                }
                $lastKey = $currentKey;
            }
        }
        if (!($entry === null)) {
            $this->addEntry($entry);
            //$entry = null;
        }

        // throw at the very end, anything recognized has been processed
        $this->unrecognizedInput = $unrecognized;
        if (count($unrecognized)) {
            throw new UnrecognizedInputException();
        }
    }
}
