<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/*
 * InvoicePlane
 *
 * @author		InvoicePlane Developers & Contributors
 * @copyright	Copyright (c) 2012 - 2018 InvoicePlane.com
 * @license		https://invoiceplane.com/license.txt
 * @link		https://invoiceplane.com
 */

/**
 * Returns the invoice image
 *
 * @return string
 */
function invoice_logo()
{
    $CI = &get_instance();

    if ($CI->mdl_settings->setting('invoice_logo')) {
        return '<img src="' . base_url() . 'uploads/' . $CI->mdl_settings->setting('invoice_logo') . '">';
    }

    return '';
}

/**
 * Returns the invoice logo for PDF files
 *
 * @return string
 */
function invoice_logo_pdf()
{
    $CI = &get_instance();

    if ($CI->mdl_settings->setting('invoice_logo')) {
        $absolutePath = dirname(dirname(__DIR__));
        return '<img src="' . $absolutePath . '/uploads/' . $CI->mdl_settings->setting('invoice_logo') . '" id="invoice-logo">';
    }

    return '';
}


/**
 * Returns a Swiss IS / IS+ code line
 * Documentation: https://www.postfinance.ch/binp/postfinance/public/dam.M26m_i6_6ceYcN2XtAN4w8OHMynQG7FKxJVK8TtQzr0.spool/content/dam/pf/de/doc/consult/manual/dlserv/inpayslip_isr_man_en.pdf
 *
 * @param string $slipType
 * @param $amount
 * @param string $rnumb
 * @param $subNumb
 * @return string
 * @throws Error
 */
function invoice_genCodeline($slipType, $amount, $rnumb, $subNumb)
{
    $isEur = false;

    if ((int)$slipType > 14) {
        $isEur = true;
    } else {
        $amount = .5 * round((float)$amount / .5, 1);
    }

    if (!$isEur && $amount > 99999999.95) {
        throw new Error("Invalid amount");
    } elseif ($isEur && $amount > 99999999.99) {
        throw new Error("Invalid amount");
    }

    $amountLine = sprintf("%010d", $amount * 100);
    $checkSlAmount = invoice_recMod10($slipType . $amountLine);

    if (!preg_match("/\d{2}-\d{1,6}-\d{1}/", $subNumb)) {
        throw new Error("Invalid subscriber number");
    }

    $subNumb = explode("-", $subNumb);
    $fullSub = $subNumb[0] . sprintf("%06d", $subNumb[1]) . $subNumb[2];
    $rnumb = preg_replace('/\s+/', '', $rnumb);

    return $slipType . $amountLine . $checkSlAmount . ">" . $rnumb . "+ " . $fullSub . ">";
}

/**
 * Calculate checksum using Recursive Mod10
 * See https://www.postfinance.ch/binp/postfinance/public/dam.Ii-X5NgtAixO8cQPvja46blV6d7cZCyGUscxO15L5S8.spool/content/dam/pf/de/doc/consult/manual/dldata/efin_recdescr_man_en.pdf
 * Page 5
 *
 * @param string $in
 * @return integer
 */
function invoice_recMod10($in)
{
    $line = [0, 9, 4, 6, 8, 2, 7, 1, 3, 5];
    $carry = 0;
    $chars = str_split($in);

    foreach ($chars as $char) {
        $carry = $line[($carry + intval($char)) % 10];
    }

    return (10 - $carry) % 10;
}

/**
 * Returns a QR code for invoice payments
 *
 * @param number invoice-id
 * @return string
 */
function invoice_qrcode($invoice_id) {
    $CI = &get_instance();

    if (
        $CI->mdl_settings->setting('qr_code')
        && $CI->mdl_settings->setting('qr_code_iban')
        && $CI->mdl_settings->setting('qr_code_bic')
    ) {
        $invoice = $CI->mdl_invoices->get_by_id($invoice_id);
        $CI->load->library('QrCode', [ 'invoice' => $invoice ]);
        $qrcode_data_uri = $CI->qrcode->generate();

        return '<img src="' . $qrcode_data_uri . '" alt="QR Code" id="invoice-qr-code">';
    }

    return '';
}

/**
 * Retrieve locale for clear text language
 *
 * @param string displayName
 * @param string default_locale
 * @return string
 */
function getLocaleByDisplayName($displayName, $default_locale = 'en') {
    $CI = &get_instance();

    // https://www.php.net/manual/de/class.resourcebundle.php
    // get all available locales
    $allLocales = ResourceBundle::getLocales('');
    foreach ($allLocales as $locale) {
        // https://www.php.net/manual/de/class.locale.php
        // get string with langauge part for locale
        $currentName = Locale::getDisplayLanguage($locale, $default_locale);
        if (strncmp($currentName, $displayName, strlen($currentName)) === 0) {
            // use first/shortest locale match

            return $locale;
        }
    }

    return $default_locale;
}

/**
 * Replace date tags within description with useful real date values
 *
 * @param date invoice_date_created
 * @param string client_language
 * @param string item_description
 *   Tags: can have the form {{{month}}}, {{{date}}}, {{{year}}}, {{{month+1}}} etc.
 * @return string
*/
function replaceDateTags($invoice_date_created, $client_language, $item_description) {
    $CI = &get_instance();

    $INVOICE_DATE_CREATED = new DateTime(date_from_mysql($invoice_date_created, true));
    
    if ( 'system' == $client_language ) $DATE_LANGUAGE_LOCALE = get_setting('default_country');
    else $DATE_LANGUAGE_LOCALE = getLocaleByDisplayName($client_language);

    // initialize based date
    $PRINT_DATE = clone($INVOICE_DATE_CREATED);
    // get the tags
    $replacements = explode('{{{', $item_description);
    foreach ($replacements as $replacement) {
        // find tags end
        $replace = stristr($replacement, '}}}', true);
        // nothing to do here
        if (empty($replace)) continue;

        // here we do D(ate), M(onth and year) and Y(ear) nothing else
        $request = strtoupper(substr($replace,0,1));  
        // take only first letter, ignore if not within our service
        if (strpos('DMY', $request) === false) continue;

        // reconstruct original replacement
        $replacement='{{{'.$replace.'}}}';

        // needs to reset if a new relative date occurs
        try {
            // calculate additions/substractions
            if ($pos = strpos($replace, '+')) {
                $num = substr($replace,$pos+1);
                // refresh date to calculate with
                $PRINT_DATE = clone($INVOICE_DATE_CREATED);
                $PRINT_DATE->add(new DateInterval( 'P' . $num . $request ));
            }
            elseif ($pos = strpos($replace, '-')) {
                $num = substr($replace,$pos+1);
                // refresh date to calculate with
                $PRINT_DATE = clone($INVOICE_DATE_CREATED);
                $PRINT_DATE->sub(new DateInterval( 'P' . $num . $request ));
            }
            
            // prepare replacement format string
            if ('D' == $request) {
                // ignore locale and create sql Y-m-d format date
                // and use ip's function to create a visible date
                $replace = date_from_mysql(date_format($PRINT_DATE, 'Y-m-d'));
            }
            else {
                if ('M' == $request) $pattern = 'MMM yyyy'; // short month year
                elseif ('Y' == $request) $pattern = 'yyyy';     // year only
                $DT_FORMAT = new IntlDateFormatter(
                                    $DATE_LANGUAGE_LOCALE,
                                    IntlDateFormatter::SHORT,
                                    IntlDateFormatter::SHORT
                                    );
                $DT_FORMAT->setPattern($pattern);
                $replace = datefmt_format($DT_FORMAT, $PRINT_DATE);
            }

            // replace within item description
            $item_description = str_replace($replacement, $replace, $item_description);

        } catch (Exception $e) {
            echo 'ERROR Message: ' .$e->getMessage();
        }
                
    return $item_description;
}
