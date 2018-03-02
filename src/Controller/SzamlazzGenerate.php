<?php

namespace Drupal\commerce_szamlazz\Controller;

use Drupal\commerce\Context;
use Drupal\Core\Controller\ControllerBase;

/**
 * Szamlazz class.
 */
class SzamlazzGenerate extends ControllerBase {

  protected $xml;
  protected $xml_invoice;
  protected $config;
  protected $order;

  /**
   * Implements szmalazz.hu invoice generation code.
   */
  public function generate($commerce_order) {
    if (!$commerce_order) {
      return NULL;
    }
    if (isset($commerce_order->szamlazz_invoice_id->value)) {
      $invoice_link = '<a href="' . $commerce_order->szamlazz_invoice_id->uri .
      '">' . $commerce_order->szamlazz_invoice_id->title . '</a>';
      drupal_set_message(t('Order already invoiced: <b>@invoice_number</b>',
        ['@invoice_number' => $commerce_order->szamlazz_invoice_id->value]),
      'error');
      return [
        '#type' => 'markup',
        '#prefix' => '<a href="' . $_SERVER['HTTP_REFERER'] . '">',
        '#suffix' => '</a>',
        '#markup' => t('Return to previous page'),
      ];
    }
    $this->order = $commerce_order;

    // $service = \Drupal::service('commerce_tax.tax_order_processor');
    // $tax_type_storage = $service->process($commerce_order);
    $config       = \Drupal::config('commerce_szamlazz.settings');
    $this->config = $config;
    $profile      = $commerce_order->getBillingProfile();
    $data         = $profile->get('address')->getValue();
    $address      = [];
    if (isset($data[0])) {
      $address = $data[0];
    }
    $ordered_products = $commerce_order->getItems();

    // TODO-: set usable charset. (this is used in the example
    // they have in the documentation)
    $this->xml = new \DOMDocument("1.0", "ISO-8859-2");
    $test = $this->prepareXmlHeader($config);
    if (!$test) {
      return [
        '#type' => 'markup',
        '#prefix' => '<div>',
        '#suffix' => '</div>',
        '#markup' => '',
      ];
    }
    else {
      $this->setSeller();
      $check = $this->setCustomer($commerce_order, $address);
      if ($check == FALSE) {
        drupal_set_message(t('Addres incomplete'), 'error', FALSE);

        return [
          '#type' => 'markup',
          '#prefix' => '<div>',
          '#suffix' => '</div>',
          '#markup' => '',
        ];
      }
      $this->setProductLines($ordered_products);
      $this->xml->appendChild($this->xml_invoice);

      return $this->sendData($this->xml->saveXML());
    }
  }

  /**
   * Prepare xml header.
   *
   * @param object $config
   */
  protected function prepareXmlHeader($config) {
    $this->xml_invoice = $this->xml->createElement('xmlszamla');
    $this->xml_invoice->setAttribute('xmlns', 'http://www.szamlazz.hu/xmlszamla');
    $this->xml_invoice->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $this->xml_invoice->setAttribute('xsi:schemaLocation', 'http://www.szamlazz.hu/xmlszamla xmlszamla.xsd ');

    $xml_invoice_settings = $this->xml->createElement('beallitasok');
    $agent_user           = $config->get('szamlazz_user') ? $config->get('szamlazz_user') : FALSE;
    $agent_pass           = $config->get('szamlazz_password') ? $config->get('szamlazz_password') : FALSE;

    // todo-: break operation if agent user and password is empty.
    if (!$agent_user || !$agent_pass || strlen($agent_user) == 0) {
      // Throw new \exception('szamlazz.hu api user not set!');
      // .
      $user = \Drupal::currentUser()->getRoles();
      if (in_array("administrator", $user)) {
        drupal_set_message(t('Api credentials are not set!! Please set them <a href="/admin/commerce/config/szamlazz">Here</a>'), 'error');
      }
      else {
        drupal_set_message(t('Szamlazz api is not set correctly please contact the site administrator!'), 'error');
      }
      return FALSE;
    }

    $xml_invoice_settings->appendChild($this->xml->createElement('felhasznalo', $agent_user));
    $xml_invoice_settings->appendChild($this->xml->createElement('jelszo', $agent_pass));
    $xml_invoice_settings->appendChild($this->xml->createElement('eszamla', 'true'));
    $xml_invoice_settings->appendChild($this->xml->createElement('szamlaLetoltes', 'true'));
    $xml_invoice_settings->appendChild($this->xml->createElement('szamlaLetoltesPld', '2'));
    $xml_invoice_settings->appendChild($this->xml->createElement('valaszVerzio', '1'));
    $this->xml_invoice->appendChild($xml_invoice_settings);

    $xml_invoice_header = $this->xml->createElement('fejlec');
    $xml_invoice_header->appendChild($this->xml->createElement('keltDatum', date('Y-m-d')));
    $xml_invoice_header->appendChild($this->xml->createElement('teljesitesDatum', date('Y-m-d')));
    $xml_invoice_header->appendChild($this->xml->createElement('fizetesiHataridoDatum', date('Y-m-d')));
    $xml_invoice_header->appendChild($this->xml->createElement('fizmod', 'Készpénz'));
    $xml_invoice_header->appendChild($this->xml->createElement('penznem', 'Ft'));
    $xml_invoice_header->appendChild($this->xml->createElement('szamlaNyelve', 'hu'));
    $xml_invoice_header->appendChild($this->xml->createElement('megjegyzes', ''));
    $xml_invoice_header->appendChild($this->xml->createElement('elolegszamla', 'false'));
    $xml_invoice_header->appendChild($this->xml->createElement('vegszamla', 'false'));
    $xml_invoice_header->appendChild($this->xml->createElement('dijbekero', 'false'));
    $this->xml_invoice->appendChild($xml_invoice_header);
    return TRUE;
  }

  /**
   * Set sellers for xml document.
   */
  protected function setSeller() {
    $xml_invoice_seller = $this->xml->createElement('elado');
    $this->xml_invoice->appendChild($xml_invoice_seller);
  }

  /**
   * Set customer for xml document.
   *
   * @param mixed $order
   * @param mixed $address
   */
  protected function setCustomer($order, $address) {
    $xml_invoice_buyer = $this->xml->createElement('vevo');
    // TODO: check address arrays are valid.
    if (!isset($address['family_name']) ||
      !isset($address['given_name']) ||
      !isset($address['postal_code']) ||
      !is_numeric($address['postal_code']) ||
      !isset($address['locality']) ||
      !isset($address['address_line1'])
    ) {
      return FALSE;
    }
    $xml_invoice_buyer->appendChild($this->xml->createElement('nev', $address['family_name'] . ' ' . $address['given_name']));
    $xml_invoice_buyer->appendChild($this->xml->createElement('irsz', $address['postal_code']));
    $xml_invoice_buyer->appendChild($this->xml->createElement('telepules', $address['locality']));
    $xml_invoice_buyer->appendChild($this->xml->createElement('cim', $address['address_line1']));
    $xml_invoice_buyer->appendChild($this->xml->createElement('email', $order->get('mail')->getValue()[0]['value']));
    $this->xml_invoice->appendChild($xml_invoice_buyer);
    return TRUE;
  }

  /**
   * Set product lines for xml document.
   *
   * @param object $ordered_products
   */
  protected function setProductLines($ordered_products) {
    $xml_invoice_line_items = $this->xml->createElement('tetelek');

    // Net price resolver.
    $price_chain_resolver = \Drupal::service('commerce_price.chain_price_resolver');
    $context              = new Context(\Drupal::currentUser(), \Drupal::service('commerce_store.current_store')->getStore());

    foreach ($ordered_products as $key => $value) {
      $net_unit_price = $price_chain_resolver->resolve($value->getPurchasedEntity(), 1, $context);
      $net_unit_price = $net_unit_price->getNumber();
      $adjustments = $value->get('adjustments')->getValue();

      foreach ($adjustments as $adjustment) {
        $type = $adjustment['value']->getType();
        if ($type === 'tax') {
          $tax_percent = $adjustment['value']->getPercentage() * 100;
          $tax_value   = $adjustment['value']->getAmount()->getNumber();
        }
      }

      $xml_invoice_line_item = $this->xml->createElement('tetel');
      $quantity = $value->get('quantity')->value;
      $total_net = $net_unit_price * $quantity;
      $total_tax = $tax_value * $quantity;

      $xml_invoice_line_item->appendChild($this->xml->createElement('megnevezes', $value->get('title')->value));
      $xml_invoice_line_item->appendChild($this->xml->createElement('mennyiseg', $quantity));
      $xml_invoice_line_item->appendChild($this->xml->createElement('mennyisegiEgyseg', 'db'));
      $xml_invoice_line_item->appendChild($this->xml->createElement('nettoEgysegar', $net_unit_price));
      $xml_invoice_line_item->appendChild($this->xml->createElement('afakulcs', $tax_percent));
      $xml_invoice_line_item->appendChild($this->xml->createElement('nettoErtek', $total_net));
      $xml_invoice_line_item->appendChild($this->xml->createElement('afaErtek', $total_tax));
      $xml_invoice_line_item->appendChild($this->xml->createElement('bruttoErtek', $total_net + $total_tax));
      $xml_invoice_line_items->appendChild($xml_invoice_line_item);
    }

    $this->xml_invoice->appendChild($xml_invoice_line_items);
  }

  /**
   * Send data to szamlazz.hu agent.
   *
   * @param string $xmltext
   *
   * @return array
   *
   * @throws \exception
   */
  protected function sendData($xmltext) {
    $drupal_tmpfname = drupal_tempnam('private://', "szamlazzxml");
    $tmpfname        = drupal_realpath($drupal_tmpfname);
    $handle          = fopen($tmpfname, "w");
    fwrite($handle, $xmltext);
    fclose($handle);

    $agent_url = $this->config->get('szamlazz_agent_url');

    $download_invoice = TRUE;
    $ch               = curl_init($agent_url);

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
    $http_error     = curl_error($ch);

    $agent_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $agent_header = substr($agent_response, 0, $header_size);

    preg_match_all('|Set-Cookie: (.*);|U', $agent_header, $cookie_results);
    $_SESSION['szamlazz_cookie'] = implode(';', $cookie_results[1]);

    $agent_body = substr($agent_response, $header_size);
    $http_error = curl_error($ch);
    curl_close($ch);
    kint($http_error);    $http_error = 'Could not resolve host: www.szamlazz.hu';
    $dbgt = debug_backtrace();
    kint($dbgt);
    kint($dbgt[0]['line']);
    $line = $dbgt[0]['line'];
    if (strlen($http_error) > 0) {
      drupal_set_message(t('Invoice generation failed.'), 'error');

      \Drupal::logger('commerce_szamlazz')->error('Http error with message: ' . $http_error . '(line %line of %file)');

      return [
        '#type' => 'markup',
      ];
    }
    // TODO: check http error.
    //
    // Delete temp xml file.
    drupal_unlink($drupal_tmpfname);

    $header_array = explode("\n", $agent_header);

    $is_error = FALSE;

    $agent_error      = '';
    $agent_error_code = '';
    $invoice_number   = '';
    foreach ($header_array as $val) {
      if (substr($val, 0, strlen('szlahu')) === 'szlahu') {
        if (substr($val, 0, strlen('szlahu_error:')) === 'szlahu_error:') {
          $is_error    = TRUE;
          $agent_error = substr($val, strlen('szlahu_error:'));
        }
        if (substr($val, 0, strlen('szlahu_error_code:')) === 'szlahu_error_code:') {
          $is_error         = TRUE;
          $agent_error_code = substr($val, strlen('szlahu_error_code:'));
        }
        if (substr($val, 0, strlen('szlahu_szamlaszam:')) === 'szlahu_szamlaszam:') {
          $invoice_number = trim(substr($val, strlen('szlahu_szamlaszam:')));
        }
      }
      if (substr($val, 0, strlen('Content-Disposition:')) === 'Content-Disposition:') {
        $invoicelink = explode('filename=', $val);
        $invoice_link = 'https://www.szamlazz.hu/szamla/genpdf/' . $invoicelink[1];
      }
    }

    if ($http_error != "") {

    }
    if ($is_error) {
      throw new \exception(t('Unable to create invoice.') . $agent_error_code);
    }
    else {

      // If ($download_invoice) {
      //   Save the invoice locally if necessary
      // }.
      $markup = [
        '#type' => 'markup',
        'message' => [
          '#prefix' => '<h2>',
          '#suffix' => '</h2>',
          '#markup' => t('Invoice generated successfully, invoice number: <u>@invoice</u>', ['@invoice' => $invoice_number]),
          'szamlak' => [
            '#prefix' => '<div><a href="https://www.szamlazz.hu/szamla/szamlakereso">',
            '#suffix' => '</a></div>',
            '#markup' => t('Click to view all invoces'),
          ],
        ],
        'return' => [
          '#prefix' => '<div>',
          '#suffix' => '</div>',
          'link' => [
            '#prefix' => '<a href="/admin/commerce/orders">',
            '#suffix' => '</a>',
            '#markup' => t('Return to orders'),
          ],
        ],
      ];

      $this->order->szamlazz_invoice_id->value = $invoice_number;
      $this->order->save();
      return $markup;
    }

    $result = [
      '#type'   => 'markup',
      '#markup' => 'Something went wrong!',
    ];

    return $result;
  }

}
