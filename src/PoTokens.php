<?php

namespace Geekwright\Po;

/**
 * Constants representing line identification tokens found in a GNU gettext
 * style PO or POT file
 *
 * @category  Tokens
 * @package   Po
 * @author    Richard Griffith <richard@geekwright.com>
 * @copyright 2015-2018 Richard Griffith
 * @license   GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://github.com/geekwright/Po
 */
interface PoTokens
{
    const TRANSLATOR_COMMENTS = '#';
    const EXTRACTED_COMMENTS = '#.';
    const REFERENCE = '#:';
    const FLAG = '#,';
    const PREVIOUS = '#|';
    const OBSOLETE = '#~';
    const CONTEXT = 'msgctxt';
    const MESSAGE = 'msgid';
    const PLURAL = 'msgid_plural';
    const TRANSLATED = 'msgstr';
    const TRANSLATED_PLURAL = 'msgstr[';
    const CONTINUED_DATA = '"';
}
