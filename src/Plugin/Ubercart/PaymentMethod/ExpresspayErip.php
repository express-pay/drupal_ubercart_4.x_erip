<?php

namespace Drupal\uc_expresspayerip\Plugin\Ubercart\PaymentMethod;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_payment\PaymentMethodPluginBase;

/**
 * Defines the expresspayerip payment method.
 *
 * @UbercartPaymentMethod(
 *   id = "expresspayerip",
 *   name = "Экспресс платежи: ЕРИП",
 *   redirect = "\Drupal\uc_expresspayerip\Form\expresspayeripForm",
 * )
 */
class ExpresspayErip extends PaymentMethodPluginBase implements OffsitePaymentMethodPluginInterface
{

	/**
	 * @param string $label
	 *
	 * @return mixed
	 */
	public function getDisplayLabel($label)
	{
		$build['label'] = [
			'#prefix'     => '<div class="uc-expresspayerip">',
			'#plain_text' => $label,
			'#suffix'     => '</div>',
		];
		$build['image'] = [
			'#theme'      => 'image',
			'#uri'        => drupal_get_path('module', 'uc_expresspayerip') . '/images/logo.png',
			'#alt'        => $this->t('expresspayerip'),
			'#attributes' => array('class' => array('uc-fondy-logo'))
		];

		return $build;
	}

	/**
	 * @return array
	 */
	public function defaultConfiguration()
	{
		return [
			'isTest'				=> true,
			'serviceId'   			=> '4',
			'token'       			=> 'a75b74cbcfe446509e8ee874f421bd66',
			'useSignature'      	=> true,
			'secretWord'   			=> 'sandbox.expresspay.by',
			'useSignatureForNotif'	=> false,
			'secretWordForNotif'	=> '',
			'pathToErip'			=> '',
			'isNameEdit'			=> false,
			'isAmountEdit'			=> false,
			'isAddressEdit'			=> false
		];
	}

	/**
	 * @param array $form
	 * @param FormStateInterface $form_state
	 *
	 * @return array
	 */
	public function buildConfigurationForm(array $form, FormStateInterface $form_state)
	{
		$form['isTest'] = array(
			'#type'          => 'checkbox',
			'#title'         => 'Тестовый режим',
			'#default_value' => $this->configuration['isTest'],
		);
		$form['serviceId'] = array(
			'#type'          => 'textfield',
			'#title'         => "Номер услуги",
			'#description'   => 'Можно узнать в личном кабинете сервиса "Экспресс Платежи" в настройках услуги.',
			'#default_value' => $this->configuration['serviceId'],
			'#size'          => 16,
		);
		$form['token']  = array(
			'#type'          => 'textfield',
			'#title'         => 'Токен',
			'#description'   => 'Можно узнать в личном кабинете сервиса "Экспресс Платежи" в настройках услуги.',
			'#default_value' => $this->configuration['token'],
			'#size'          => 256,
		);
		$form['useSignature'] = array(
			'#type'          => 'checkbox',
			'#title'         => 'Использовать цифровую подпись для выставления счетов',
			'#description'   => 'Значение должно совпадать со значением, установленным в личном кабинете сервиса "Экспресс Платежи".',
			'#default_value' => $this->configuration['useSignature'],
		);
		$form['secretWord']  = array(
			'#type'          => 'textfield',
			'#title'         => 'Секретное слово',
			'#description'   => 'Задается в личном кабинете, секретное слово должно совпадать с секретным словом, установленным в личном кабинете сервиса "Экспресс Платежи".',
			'#default_value' => $this->configuration['secretWord'],
			'#size'          => 256,
		);
		$form['notifUrl']    = array(
			'#type'          => 'url',
			'#title'         => 'Адрес для уведомлений',
			'#default_value' => Url::fromRoute('uc_expresspayerip.notification', [], ['absolute' => true])->toString(),
			'#attributes'    => array('readonly' => 'readony'),
		);
		$form['useSignatureForNotif'] = array(
			'#type'          => 'checkbox',
			'#title'         => 'Использовать цифровую подпись для уведомлений',
			'#description'   => 'Значение должно совпадать со значением, установленным в личном кабинете сервиса "Экспресс Платежи".',
			'#default_value' => $this->configuration['useSignatureForNotif'],
		);
		$form['secretWordForNotif']  = array(
			'#type'          => 'textfield',
			'#title'         => 'Секретное слово для уведомлений',
			'#description'   => 'Задается в личном кабинете, секретное слово должно совпадать с секретным словом, установленным в личном кабинете сервиса "Экспресс Платежи".',
			'#default_value' => $this->configuration['secretWordForNotif'],
			'#size'          => 256,
		);
		$form['pathToErip']  = array(
			'#type'          => 'textfield',
			'#title'         => 'Путь по ветке ЕРИП',
			'#default_value' => $this->configuration['pathToErip'],
			'#size'          => 256,
		);
		$form['isNameEdit'] = array(
			'#type'          => 'checkbox',
			'#title'         => 'Разрешено изменять ФИО',
			'#default_value' => $this->configuration['isNameEdit'],
		);
		$form['isAmountEdit'] = array(
			'#type'          => 'checkbox',
			'#title'         => 'Разрешено изменять сумму',
			'#default_value' => $this->configuration['isAmountEdit'],
		);
		$form['isAddressEdit'] = array(
			'#type'          => 'checkbox',
			'#title'         => 'Разрешено изменять адрес',
			'#default_value' => $this->configuration['isAddressEdit'],
		);

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
	{
		$this->configuration['isTest']     	  			= $form_state->getValue('isTest');
		$this->configuration['serviceId']       		= $form_state->getValue('serviceId');
		$this->configuration['token'] 					= $form_state->getValue('token');
		$this->configuration['useSignature']      		= $form_state->getValue('useSignature');
		$this->configuration['secretWord']      		= $form_state->getValue('secretWord');
		$this->configuration['notifUrl']      			= $form_state->getValue('notifUrl');
		$this->configuration['useSignatureForNotif']    = $form_state->getValue('useSignatureForNotif');
		$this->configuration['secretWordForNotif']   	= $form_state->getValue('secretWordForNotif');
		$this->configuration['pathToErip']   			= $form_state->getValue('pathToErip');
		$this->configuration['isNameEdit']   			= $form_state->getValue('isNameEdit');
		$this->configuration['isAmountEdit']   			= $form_state->getValue('isAmountEdit');
		$this->configuration['isAddressEdit']   		= $form_state->getValue('isAddressEdit');
	}

	/**
	 * {@inheritdoc}
	 */
	public function cartProcess(OrderInterface $order, array $form, FormStateInterface $form_state)
	{
		$session = \Drupal::service('session');
		if (null != $form_state->getValue(['panes', 'payment', 'details', 'pay_method'])) {
			$session->set('pay_method', $form_state->getValue(['panes', 'payment', 'details', 'pay_method']));
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function cartReviewTitle()
	{
		return "Оплата через систему ЕРИП";
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = null)
	{

		$settings = $this->configuration;
		$amount        = number_format(floatval($order->getTotal()), 2, ',', ''); //Формирование суммы с 2 числами после ","
		$payment  = $order->getAddress('billing');

		$request_params = array(
			'ServiceId'         => $settings['serviceId'],
			'AccountNo'         => $order->id(),
			'Amount'            => $amount,
			'Currency'          => 933,
			'ReturnType'        => 'redirect',
			'ReturnUrl'         => Url::fromRoute('uc_expresspayerip.complete', [], ['absolute' => true])->toString(),
			'FailUrl'           => Url::fromRoute('uc_expresspayerip.cancel', [], ['absolute' => true])->toString(),
			'Expiration'        => '',
			'Info'              => '',
			'Surname'           => $payment->last_name,
			'FirstName'         => $payment->first_name,
			'Patronymic'        => '',
			'Street'            => '',
			'House'             => '',
			'Apartment'         => '',
			'IsNameEditable'    => $settings['isNameEdit'] ? 1 : 0,
			'IsAddressEditable' => $settings['isAddressEdit'] ? 1 : 0,
			'IsAmountEditable'  => $settings['isAmountEdit'] ? 1 : 0,
			'EmailNotification' => $order->getEmail(),
			'SmsPhone'          => preg_replace('/[^0-9]/', '', $payment->phone)
		);

		$request_params['Signature'] = self::compute_signature($request_params, $settings['token'], $settings['secretWord']);

		$baseUrl = "https://api.express-pay.by/v1/";
		
		if($settings['isTest'])
			$baseUrl = "https://sandbox-api.express-pay.by/v1/";
		
		$url = $baseUrl . "web_invoices";

		return $this->generateForm($request_params, $url);
	}

	/**
	 * @param $data
	 * @param string $url
	 *
	 * @return mixed
	 */
	public function generateForm($data, $url)
	{
		$form['#action'] = $url;
		foreach ($data as $k => $v) {
			if (!is_array($v)) {
				$form[$k] = array(
					'#type'  => 'hidden',
					'#value' => $v
				);
			} else {
				$i = 0;
				foreach ($v as $val) {
					$form[$k . '[' . $i++ . ']'] = array(
						'#type'  => 'hidden',
						'#value' => $val
					);
				}
			}
		}
		$form['actions']           = ['#type' => 'actions'];
		$form['actions']['submit'] = [
			'#type'  => 'submit',
			'#value' => $this->t('Submit order'),
		];

		return $form;
	}

	public static function compute_signature($request_params, $token, $secret_word, $method = 'add_invoice')
	{
		$secret_word = trim($secret_word);
		$normalized_params = array_change_key_case($request_params, CASE_LOWER);
		$api_method = array(
			'add_invoice' => array(
				"serviceid",
				"accountno",
				"amount",
				"currency",
				"expiration",
				"info",
				"surname",
				"firstname",
				"patronymic",
				"city",
				"street",
				"house",
				"building",
				"apartment",
				"isnameeditable",
				"isaddresseditable",
				"isamounteditable",
				"emailnotification",
				"smsphone",
				"returntype",
				"returnurl",
				"failurl"
			),
			'get_qr_code' => array(
				"invoiceid",
				"viewtype",
				"imagewidth",
				"imageheight"
			),
			'add_invoice_return' => array(
				"accountno",
				"invoiceno"
			)
		);

		$result = $token;

		foreach ($api_method[$method] as $item)
			$result .= (isset($normalized_params[$item])) ? $normalized_params[$item] : '';

		$hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

		return $hash;
	}
}
