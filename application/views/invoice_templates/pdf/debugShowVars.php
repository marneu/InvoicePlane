<!DOCTYPE HTML>
<html>
    <body>
      <?php echo invoice_logo_pdf(); ?>
      <p>CUSTOM Variables ($custom_fields)</p>
      <pre><?php print_r($custom_fields); ?></pre>
      <hr>
      <p>ITEMS ($items)</p>
      <?php foreach ($items as $item) { ?>
        <pre><?php print_r($item); ?></pre>
        <p>Item date replacement<p>
        <pre><?php
          // this should come from a helper function
          print_r(invoice_replace_date_tags($invoice->invoice_date_created, 
                                            $invoice->client_language, 
                                            $item->item_description));
        ?></pre>
        <?php } ?>
      <hr>
      <p>TAXES ($invoice_tax_rates)</p>
      <?php foreach ($invoice_tax_rates as $invoice_tax_rate) { ?>
        <pre><?php print_r($invoice_tax_rate); ?></pre>
      <?php } ?>
      <hr>
      <p>INVOICE Variables ($invoice)</p>
      <pre><?php print_r($invoice); ?></pre>
      <hr>
      <p>System Languange</p>
      <pre><?php print_r(get_setting('default_language')); ?></pre>
      <hr>
      <p>System Country</p>
      <pre><?php print_r(get_setting('default_country')); ?></pre>
      <hr>
    </body>
</html>
