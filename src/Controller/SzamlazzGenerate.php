<?php

namespace Drupal\commerce_szamlazz\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Szamlazz class.
 */
class SzamlazzGenerate extends ControllerBase {

  /**
   * Implements szmalazz.hu invoice generation code.
   */
  public function generate($oid) {

    $config = \Drupal::config('commerce_szamlazz.settings');

    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($oid);
    $xml = new \DOMDocument("1.0", "ISO-8859-15");

    $order_data['user'] = $order->get('uid')->getValue();
    $order_data['total_price'] = $order->get('total_price')->getValue();
    $order_data['ordered_items'] = $order->get('order_items')->getValue();
    $order_data['billing_profile'] = $order->get('billing_profile')->getValue();

    $profile = $order->getBillingProfile();
    $address = $profile->get('address')->getValue()[0];
    $ordered_products = $order->getItems();

    $xml_invoice = $xml->createElement('xmlszamla');
    $xml_invoice->setAttribute('xmlns', 'http://www.commerce_szamlazz.hu/xmlszamla');
    $xml_invoice->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $xml_invoice->setAttribute('xsi:schemaLocation', 'http://www.commerce_szamlazz.hu/xmlszamla xmlszamla.xsd ');

    $xml_invoice_settings = $xml->createElement('beallitasok');
    $xml_invoice_settings->appendChild($xml->createElement('felhasznalo', $config->get('szamlazz_user')));
    $xml_invoice_settings->appendChild($xml->createElement('jelszo', $config->get('szamlazz_password')));
    $xml_invoice_settings->appendChild($xml->createElement('eszamla', 'true'));
    $xml_invoice_settings->appendChild($xml->createElement('szamlaLetoltes', 'true'));
    $xml_invoice_settings->appendChild($xml->createElement('szamlaLetoltesPld', '2'));
    $xml_invoice_settings->appendChild($xml->createElement('valaszVerzio', '1'));
    $xml_invoice->appendChild($xml_invoice_settings);

    $xml_invoice_header = $xml->createElement('fejlec');
    $xml_invoice_header->appendChild($xml->createElement('keltDatum', date('Y-m-d')));
    $xml_invoice_header->appendChild($xml->createElement('teljesitesDatum', date('Y-m-d')));
    $xml_invoice_header->appendChild($xml->createElement('fizetesiHataridoDatum', date('Y-m-d')));
    $xml_invoice_header->appendChild($xml->createElement('fizmod', 'Készpénz'));
    $xml_invoice_header->appendChild($xml->createElement('penznem', 'Ft'));
    $xml_invoice_header->appendChild($xml->createElement('szamlaNyelve', 'hu'));
    $xml_invoice_header->appendChild($xml->createElement('megjegyzes', ''));
    $xml_invoice_header->appendChild($xml->createElement('elolegszamla', 'false'));
    $xml_invoice_header->appendChild($xml->createElement('vegszamla', 'false'));
    $xml_invoice_header->appendChild($xml->createElement('dijbekero', 'false'));
    $xml_invoice->appendChild($xml_invoice_header);
    $xml_invoice_seller = $xml->createElement('elado');
    $xml_invoice->appendChild($xml_invoice_seller);

    $xml_invoice_buyer = $xml->createElement('vevo');

    $xml_invoice_buyer->appendChild($xml->createElement('nev', $address['family_name'] . ' ' . $address['given_name']));
    $xml_invoice_buyer->appendChild($xml->createElement('irsz', $address['postal_code']));
    $xml_invoice_buyer->appendChild($xml->createElement('telepules', $address['locality']));
    $xml_invoice_buyer->appendChild($xml->createElement('cim', $address['address_line1']));
    $xml_invoice_buyer->appendChild($xml->createElement('email', $order->get('mail')->getValue()[0]['value']));
    $xml_invoice->appendChild($xml_invoice_buyer);

    $xml_invoice_line_items = $xml->createElement('tetelek');

    foreach ($ordered_products as $key => $value) {
      $xml_invoice_line_item = $xml->createElement('tetel');

      $tax_value = ($value->get('unit_price')->getValue()[0]['number'] * 27) / 100;
      $xml_invoice_line_item->appendChild($xml->createElement('megnevezes', $value->get('title')->getValue()[0]['value']));
      $xml_invoice_line_item->appendChild($xml->createElement('mennyiseg', $value->get('quantity')->getValue()[0]['value']));
      $xml_invoice_line_item->appendChild($xml->createElement('mennyisegiEgyseg', 'db'));
      $xml_invoice_line_item->appendChild($xml->createElement('nettoEgysegar', $value->get('unit_price')->getValue()[0]['number']));
      $xml_invoice_line_item->appendChild($xml->createElement('afakulcs', 27));
      $xml_invoice_line_item->appendChild($xml->createElement('nettoErtek', $value->get('unit_price')->getValue()[0]['number']));
      $xml_invoice_line_item->appendChild($xml->createElement('afaErtek', $tax_value));
      $xml_invoice_line_item->appendChild($xml->createElement('bruttoErtek', $value->get('total_price')->getValue()[0]['number'] + $tax_value));
      $xml_invoice_line_items->appendChild($xml_invoice_line_item);
    }

    $xml_invoice->appendChild($xml_invoice_line_items);
    $xml->appendChild($xml_invoice);

    $xmltext = $xml->saveXML();
    $drupal_tmpfname = drupal_tempnam('private://', "szamlazzxml");
    $tmpfname = drupal_realpath($drupal_tmpfname);
    $handle = fopen($tmpfname, "w");
    fwrite($handle, $xmltext);
    fclose($handle);

    $agent_url = 'https://www.commerce_szamlazz.hu/szamla/';
    $download_invoice = TRUE;
    $ch = curl_init($agent_url);

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    if (class_exists('CURLFile')) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, ['action-xmlagentxmlfile' => new \CURLFile($tmpfname, 'application/xml')]);
      curl_setopt($ch, CURLOPT_SAFE_UPLOAD, TRUE);
    }
    else {
      curl_setopt($ch, CURLOPT_POSTFIELDS, ['action-xmlagentxmlfile' => '@' . $tmpfname]);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if ($_SESSION['szamlazz_cookie']) {
      curl_setopt($ch, CURLOPT_COOKIE, $_SESSION['szamlazz_cookie']);
    }
    $agent_response = curl_exec($ch);
    $http_error = curl_error($ch);

    $agent_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $agent_header = substr($agent_response, 0, $header_size);

    preg_match_all('|Set-Cookie: (.*);|U', $agent_header, $cookie_results);
    $_SESSION['szamlazz_cookie'] = implode(';', $cookie_results[1]);

    $agent_body = substr($agent_response, $header_size);
    $http_error = curl_error($ch);
    curl_close($ch);
    //
    // Delete temp xml file.
    drupal_unlink($drupal_tmpfname);

    $header_array = explode("\n", $agent_header);

    $is_error = FALSE;

    $agent_error = '';
    $agent_error_code = '';
    $invoice_number = '';
    foreach ($header_array as $val) {
      if (substr($val, 0, strlen('szlahu')) === 'szlahu') {
        if (substr($val, 0, strlen('szlahu_error:')) === 'szlahu_error:') {
          $is_error = TRUE;
          $agent_error = substr($val, strlen('szlahu_error:'));
        }
        if (substr($val, 0, strlen('szlahu_error_code:')) === 'szlahu_error_code:') {
          $is_error = TRUE;
          $agent_error_code = substr($val, strlen('szlahu_error_code:'));
        }
        if (substr($val, 0, strlen('szlahu_szamlaszam:')) === 'szlahu_szamlaszam:') {
          $invoice_number = trim(substr($val, strlen('szlahu_szamlaszam:')));
        }
      }
    }

    if ($http_error != "") {
    }
    if ($is_error) {
      throw new Exception(t('Unable to create invoice.') . $agent_error_code);
    }
    else {

      // If ($download_invoice) {
      //   Save the invoice locally if necessary
      // }.
      $result = [
        '#type' => 'markup',
        '#markup' => t('Invoice generated successfully!'),
      ];
      return $result;
    }

    $result = [
      '#type' => 'markup',
      '#markup' => 'Something went wrong!',
    ];
    return $result;
  }

}
