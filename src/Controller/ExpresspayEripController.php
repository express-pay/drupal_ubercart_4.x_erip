<?php

namespace Drupal\uc_expresspayerip\Controller;

use Drupal\uc_expresspayerip\Plugin\Ubercart\PaymentMethod\ExpresspayErip;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_cart\CartManagerInterface;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_payment\Plugin\PaymentMethodManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller routines for uc_Fondy.
 */
class ExpresspayEripController extends ControllerBase
{

	/**
	 * The cart manager.
	 *
	 * @var \Drupal\uc_cart\CartManager
	 */
	protected $cartManager;
	/**
	 * @var
	 */
	protected $session;

	/**
	 * Constructs a FondyController.
	 *
	 * @param \Drupal\uc_cart\CartManagerInterface $cart_manager
	 *   The cart manager.
	 */
	public function __construct(CartManagerInterface $cart_manager)
	{
		$this->cartManager = $cart_manager;
	}

	/**
	 * @param ContainerInterface $container
	 *
	 * @return static
	 */
	public static function create(ContainerInterface $container)
	{
		return new static(
			$container->get('uc_cart.manager')
		);
	}

	/**
	 * Final redirec status Fondy
	 *
	 * @param int $cart_id
	 * @param Request $request
	 *
	 * @return array
	 */
	public function complete()
	{

		$orderId = $_REQUEST['ExpressPayAccountNumber'];
		$signature = $_REQUEST['Signature'];
		$order = Order::load($orderId);


		if (!$order) {
			return ['#plain_text' => "Заказ не найден!"];
		}

		$plugin = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);

		if ($plugin->getPluginId() != 'expresspayerip') {
			throw new AccessDeniedHttpException();
		}

		$configuration = $plugin->getConfiguration();

		$valid = $this->validSignature($configuration, $signature);

		if ($valid == false) {
			uc_order_comment_save($order->id(), 0, 'Эксперсс платежи: ЕРИП -> Цифровая подпись не совпала!', 'admin');
			return ['#plain_text' => "Эксперсс платежи: ЕРИП -> Цифровая подпись не совпала!"];
		}

		$order->save();

		$output =
		'<table style="width: 100%;text-align: left;">
		<tbody>
			<tr>
				<td valign="top" style="text-align:left;">
					<h3>Ваш номер заказа: ##ORDER_ID##</h3>
				Вам необходимо произвести платеж в любой системе, позволяющей проводить оплату через ЕРИП (пункты банковского обслуживания, банкоматы, платежные терминалы, системы интернет-банкинга, клиент-банкинга и т.п.).
			<br />
			<br />1. Для этого в перечне услуг ЕРИП перейдите в раздел:  <b>##ERIP_PATH##</b> <br />
			<br />2. В поле <b>"Номер заказа"</b> введите <b>##ORDER_ID##</b> и нажмите "Продолжить" <br />
			<br />3. Укажите сумму для оплаты <b>##AMOUNT##</b><br />
			<br />4. Совершить платеж.<br />
		</td>
		</tr>
		</tbody>
		</table>
		<br />';

		$output = str_replace('##ORDER_ID##', $order->id(),  $output);
		$output = str_replace('##ERIP_PATH##', $configuration['pathToErip'],  $output);
		$output = str_replace('##AMOUNT##', number_format(floatval($order->getTotal()), 2, ',', ''),  $output);

		$this->cartManager->completeSale($order);
		return ['#markup' => $output];
	}

	/**
	 * Final redirec status Fondy
	 *
	 * @param int $cart_id
	 * @param Request $request
	 *
	 * @return array
	 */
	public function cancel()
	{
		$output_error =
			'<br />
		<h3>Ваш номер заказа: ##ORDER_ID##</h3>
		<p>При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина</p>';

		return ['#markup' => $output_error];
	}

	/**
	 * 
	 * Уведомления на сайт от сервиса Экспресс платежи.
	 * 
	 */
	public function notification()
	{
		if ($_SERVER['REQUEST_METHOD'] === 'GET') {
			die('Test OK!');
		}
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$json = $_POST['Data'];
			$signature = $_POST['Signature'];
			// Преобразуем из JSON в Object
			$data = json_decode($json);
			$order = Order::load($data->AccountNo);
			$plugin = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);
			if ($plugin->getPluginId() != 'expresspayerip') {
				die('FAILED | wrong module');
				header("HTTP/1.0 400 Bad Request");
			}
			$configuration = $plugin->getConfiguration();
			if ($order) {
				if ($configuration['useSignatureForNotif']) {
					if ($signature == $this->computeSignature($json, $configuration['secretWordForNotif'])) {
						$this->updateOrder($data);
						die('OK | payment received');
						header("HTTP/1.0 200 OK");
					} else {
						die('FAILED | wrong notify signature');
						header("HTTP/1.0 400 Bad Request");
					}
				} else {
					$this->updateOrder($data);
					die('OK | payment received');
					header("HTTP/1.0 200 OK");
				}
			} else {
				die('FAILED | ID заказа неизвестен');
				header("HTTP/1.0 200 Bad Request");
			}
		}
	}

	// обновление статуса заказа
	function updateOrder($data)
	{
		// Изменился статус счета
		if ($data->CmdType == '3') {
			// Счет оплачен
			if ($data->Status == '3' || $data->Status == '6') {
				// получение заказа по номеру лицевого счета
				$order = Order::load($data->AccountNo);

				// заказ существует
				if (isset($order)) {
					$order->setStatusId('payment_received')->save();
				}
			}
			// Счет отменён
			if ($data->Status == '5') {

				// получение заказа по номеру лицевого счета
				$order = Order::load($data->AccountNo);

				// заказ существует
				if (isset($order)) {
					$order->setStatusId('canceled')->save();
					die('OK | canceled');
				}
			}
		}
	}

	function validSignature($settings, $signature)
	{
		$token = $settings['token'];
		$secret_word = $settings['secretWord'];

		$signature_param = array(
			"AccountNo" => $_REQUEST['ExpressPayAccountNumber'],
			"InvoiceNo" => $_REQUEST['ExpressPayInvoiceNo'],
		);

		$validSignature = ExpresspayErip::compute_signature($signature_param, $token, $secret_word, 'add_invoice_return');

		return $validSignature == $signature;
	}

	// Функция генерации и проверки цифровой подписи для уведомлений
	function computeSignature($json, $secretWord)
	{
		$hash = NULL;

		if (empty(trim($secretWord)))
			$hash = strtoupper(hash_hmac('sha1', $json, ""));
		else
			$hash = strtoupper(hash_hmac('sha1', $json, $secretWord));
		return $hash;
	}
}
