<?php

namespace craft\commerce\eway\gateways;

use Craft;
use craft\commerce\errors\PaymentException;
use craft\commerce\eway\EwayPaymentBundle;
use craft\commerce\eway\models\EwayPaymentForm;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\omnipay\base\CreditCardGateway;
use craft\helpers\StringHelper;
use craft\web\View;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Eway\Message\RapidResponse;
use Omnipay\Omnipay;
use Omnipay\Eway\RapidDirectGateway as OmnipayGateway;

/**
 * Gateway represents eWay Rapid Direct gateway
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     1.0
 */
class Gateway extends CreditCardGateway
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $apiKey;

    /**
     * @var string
     */
    public $password;

    /**
     * @var boolean
     */
    public $testMode;

    /**
     * @var string
     */
    public $CSEKey;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'eWay Rapid');
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params)
    {
        $defaults = [
            'gateway' => $this,
            'paymentForm' => $this->getPaymentFormModel()
        ];

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $view->registerJsFile('https://secure.ewaypayments.com/scripts/eCrypt.min.js');
        $view->registerAssetBundle(EwayPaymentBundle::class);

        $html = Craft::$app->getView()->renderTemplate('commerce-eway/paymentForm', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel()
    {
        return new EwayPaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('commerce-eway/gatewaySettings', ['gateway' => $this]);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var OmnipayGateway $gateway */
        $gateway = Omnipay::create($this->getGatewayClassName());

        $gateway->setApiKey($this->apiKey);
        $gateway->setPassword($this->password);
        $gateway->setTestMode($this->testMode);

        return $gateway;
    }

    /**
     * @inheritdoc
     */
    protected function extractCardReference(ResponseInterface $response): string
    {
        /** @var RapidResponse $response */
        if ($response->getCode() !== 'A2000') {
            throw new PaymentException($response->getMessage());
        }

        return $response->getCardReference();
    }


    /**
     * @inheritdoc
     */
    protected function extractPaymentSourceDescription(ResponseInterface $response): string
    {
        $data = $response->getData();

        return Craft::t('commerce-eway', 'Payment card {masked}', ['masked' => $data['Customer']['CardDetails']['Number']]);
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName()
    {
        return '\\'.OmnipayGateway::class;
    }

    /**
     * @inheritdoc
     */
    public function populateRequest(array &$request, BasePaymentForm $paymentForm = null)
    {
        /** @var EwayPaymentForm $paymentForm */
        if ($paymentForm) {
            $request['encryptedCardNumber'] = $paymentForm->encryptedCardNumber ?? null;
            $request['encryptedCardCvv'] = $paymentForm->encryptedCardCvv ?? null;

            $request['cardReference'] = $paymentForm->cardReference ?? null;
        }



    }
}
