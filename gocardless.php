<?php
/**
 * GoCardless Gateway.
 *
 * @package blesta
 * @subpackage blesta.components.gateways.gocardless
 * @copyright Copyright (c) 2018, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Gocardless extends NonmerchantGateway
{
    /**
     * @var string The version of this gateway
     */
    private static $version = '1.0.0';

    /**
     * @var string The authors of this gateway
     */
    private static $authors = [['name' => 'Phillips Data, Inc.', 'url' => 'http://www.blesta.com']];

    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway.
     */
    public function __construct()
    {
        // Load components required by this gateway
        Loader::loadComponents($this, ['Input', 'Session']);

        // Load the language required by this gateway
        Language::loadLang('gocardless', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Returns the name of this gateway.
     *
     * @return string The common name of this gateway
     */
    public function getName()
    {
        return Language::_('Gocardless.name', true);
    }

    /**
     * Returns the version of this gateway.
     *
     * @return string The current version of this gateway
     */
    public function getVersion()
    {
        return self::$version;
    }

    /**
     * Returns the name and URL for the authors of this gateway.
     *
     * @return array The name and URL of the authors of this gateway
     */
    public function getAuthors()
    {
        return self::$authors;
    }

    /**
     * Return all currencies supported by this gateway.
     *
     * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this gateway supports
     */
    public function getCurrencies()
    {
        return ['AUD', 'DKK', 'EUR', 'GBP', 'NZD', 'SEK'];
    }

    /**
     * Sets the currency code to be used for all subsequent payments.
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway.
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway.
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = [
            'access_token' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['isPassword', 40],
                    'message' => Language::_('Gocardless.!error.access_token.valid', true)
                ]
            ],
            'webhook_secret' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['isPassword', 40],
                    'message' => Language::_('Gocardless.!error.webhook_secret.valid', true)
                ]
            ],
            'dev_mode' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', ['true', 'false']],
                    'message' => Language::_('Gocardless.!error.dev_mode.valid', true)
                ]
            ]
        ];

        // Set checkbox if not set
        if (!isset($meta['dev_mode'])) {
            $meta['dev_mode'] = 'false';
        }

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);

        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database.
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['access_token', 'webhook_secret'];
    }

    /**
     * Sets the meta data for this particular gateway.
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form.
     *
     * @param array $contact_info An array of contact info including:
     *  - id The contact ID
     *  - client_id The ID of the client this contact belongs to
     *  - user_id The user ID this contact belongs to (if any)
     *  - contact_type The type of contact
     *  - contact_type_id The ID of the contact type
     *  - first_name The first name on the contact
     *  - last_name The last name on the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - address1 The address 1 line of the contact
     *  - address2 The address 2 line of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     *  - recur An array of recurring info including:
     *      - start_date The date/time in UTC that the recurring payment begins
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in
     *          conjunction with term in order to determine the next recurring payment
     * @return mixed A string of HTML markup required to render an authorization and
     *  capture payment form, or an array of HTML markup
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        // Load the models required
        Loader::loadModels($this, ['Companies', 'Clients']);

        // Load the GoCardless API
        $api = $this->getApi($this->meta['access_token'], $this->meta['dev_mode']);

        // Force 2-decimal places only
        $amount = number_format($amount, 2, '.', '');

        if (isset($options['recur']['amount'])) {
            $options['recur']['amount'] = round($options['recur']['amount'], 2);
        }

        // Get company information
        $company = $this->Companies->get(Configure::get('Blesta.company_id'));

        // Get client data
        $client = $this->Clients->get($contact_info['client_id']);

        // Set all invoices to pay
        if (isset($invoice_amounts) && is_array($invoice_amounts)) {
            $invoices = $this->serializeInvoices($invoice_amounts);
        }

        // Check if this transaction is eligible for subscription
        $recurring = false;

        if ($this->ifSet($options['recur']) &&
            $this->ifSet($options['recur']['amount']) > 0 &&
            $this->ifSet($options['recur']['amount']) == $amount &&
            $this->ifSet($options['recur']['period']) !== 'day'
        ) {
            $recurring = true;
        }

        // Check the payment type
        $pay_type = null;

        if ($this->ifSet($_GET['pay_type'], $_POST['pay_type']) == 'subscribe') {
            $pay_type = 'subscribe';
        } elseif ($this->ifSet($_GET['pay_type'], $_POST['pay_type']) == 'onetime') {
            $pay_type = 'onetime';
        }

        // Validate the redirect flow id
        if (isset($_GET['redirect_flow_id'])) {
            try {
                // Fetch the previous saved session token
                $session_token = $this->Session->read('gocardless_token');

                // Complete the redirect flow if isn't complete already
                $params = [
                    'params' => [
                        'session_token' => $session_token
                    ]
                ];
                $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($params), 'input', true);
                $redirect_flow = $api->redirectFlows()->complete($this->ifSet($_GET['redirect_flow_id']), $params);

                // Log the API response
                $this->log(
                    $this->ifSet($_SERVER['REQUEST_URI']),
                    serialize($redirect_flow),
                    'output',
                    $this->getResponseStatus($redirect_flow)
                );

                // Create payment or subscription
                if ($pay_type == 'subscribe') {
                    // Set the inverval unit
                    $interval_unit = null;
                    switch ($this->ifSet($options['recur']['period'])) {
                        case 'week':
                            $interval_unit = 'weekly';
                            break;
                        case 'month':
                            $interval_unit = 'monthly';
                            break;
                        case 'year':
                            $interval_unit = 'yearly';
                            break;
                    }

                    // Create subscription payment
                    $params = [
                        'params' => [
                            'amount' => $this->ifSet($options['recur']['amount']) * 100,
                            'currency' => $this->ifSet($this->currency),
                            'interval_unit' => $this->ifSet($interval_unit),
                            'interval' => $this->ifSet($options['recur']['term']),
                            'metadata' => [
                                'invoices' => $this->ifSet($invoices),
                                'client_id' => $this->ifSet($contact_info['client_id'])
                            ],
                            'links' => [
                                'mandate' => $this->ifSet(
                                    $redirect_flow->api_response->body->redirect_flows->links->mandate
                                )
                            ]
                        ]
                    ];

                    if ($interval_unit !== 'weekly') {
                        $params['day_of_month'] = date('d');
                    }

                    if ($interval_unit == 'yearly') {
                        $params['month'] = strtolower(date('F'));
                    }

                    $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($params), 'input', true);
                    $subscription = $api->subscriptions()->create($params);

                    // Log the API response
                    $this->log(
                        $this->ifSet($_SERVER['REQUEST_URI']),
                        serialize($subscription),
                        'output',
                        $this->getResponseStatus($subscription)
                    );

                    // Redirect to the return url
                    $return_url = $this->generateReturnUrl($this->ifSet($options['return_url']), [
                        'subscription_id' => $this->ifSet($subscription->api_response->body->subscriptions->id)
                    ]);
                    $this->redirectToUrl($return_url);
                } elseif ($pay_type == 'onetime') {
                    // Create one time payment
                    $params = [
                        'params' => [
                            'amount' => $this->ifSet($amount) * 100,
                            'currency' => $this->ifSet($this->currency),
                            'metadata' => [
                                'invoices' => $this->ifSet($invoices),
                                'client_id' => $this->ifSet($contact_info['client_id'])
                            ],
                            'links' => [
                                'mandate' => $this->ifSet(
                                    $redirect_flow->api_response->body->redirect_flows->links->mandate
                                )
                            ]
                        ]
                    ];
                    $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($params), 'input', true);
                    $payment = $api->payments()->create($params);

                    // Log the API response
                    $this->log(
                        $this->ifSet($_SERVER['REQUEST_URI']),
                        serialize($payment),
                        'output',
                        $this->getResponseStatus($subscription)
                    );

                    // Redirect to the return url
                    $return_url = $this->generateReturnUrl($this->ifSet($options['return_url']), [
                        'payment_id' => $this->ifSet($payment->api_response->body->payments->id)
                    ]);
                    $this->redirectToUrl($return_url);
                }
            } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
                $this->Input->setErrors(
                    ['internal' => ['response' => $e->getMessage()]]
                );
            }
        } elseif (!empty($pay_type)) {
            // Build successful redirect url for the redirect flow
            $redirect_url = $this->generateReturnUrl(null, [
                'pay_type' => $pay_type
            ]);

            // Generate a new session token
            $this->Session->clear('gocardless_token');
            $session_token = 'SESS_' . base64_encode(md5(uniqid() . $this->ifSet($contact_info['client_id'])));
            $this->Session->write('gocardless_token', $session_token);

            // Create a new redirect flow
            try {
                $params = [
                    'params' => [
                        'description' => $this->ifSet($options['description'], $company->name),
                        'session_token' => $session_token,
                        'success_redirect_url' => $redirect_url,
                        'prefilled_customer' => [
                            'given_name' => $this->ifSet($client->first_name),
                            'family_name' => $this->ifSet($client->last_name),
                            'email' => $this->ifSet($client->email),
                            'address_line1' => $this->ifSet($client->address1),
                            'address_line2' => $this->ifSet($client->address2),
                            'city' => $this->ifSet($client->city),
                            'region' => $this->ifSet($client->state),
                            'postal_code' => $this->ifSet($client->zip),
                            'country_code' => $this->ifSet($client->country),
                            'company_name' => $this->ifSet($client->company)
                        ]
                    ]
                ];
                $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($params), 'input', true);
                $redirect_flow = $api->redirectFlows()->create($params);

                // Log the API response
                $this->log(
                    $this->ifSet($_SERVER['REQUEST_URI']),
                    serialize($redirect_flow),
                    'output',
                    $this->getResponseStatus($redirect_flow)
                );

                // Redirect to the authorization page
                $this->redirectToUrl($this->ifSet($redirect_flow->redirect_url));
            } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
                $this->Input->setErrors(
                    ['internal' => ['response' => $e->getMessage()]]
                );
            }
        }

        return $this->buildForm($recurring);
    }

    /**
     * Builds the HTML form.
     *
     * @param bool $recurring True if this is a recurring payment request, false otherwise
     * @return string The HTML form
     */
    private function buildForm($recurring = false)
    {
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('recurring', $recurring);

        return $this->view->fetch();
    }

    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's
     *      original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        // Get the request body posted by the GoCardless API
        $request = file_get_contents('php://input');

        // Validate the webhook call
        $headers = getallheaders();
        $signature_header = $headers['Webhook-Signature'];

        // Load the GoCardless API
        $api = $this->getApi($this->meta['access_token'], $this->meta['dev_mode']);

        // Parse the webhook request
        try {
            $events = \GoCardlessPro\Webhook::parse($request, $signature_header, $this->meta['webhook_secret']);

            $event_fields = [];

            foreach ($events as $event) {
                $resource = rtrim($event->resource_type, 's');

                if (isset($event->links[$resource])) {
                    $event_fields[$resource . '_id'] = $event->links[$resource];
                }
                if (isset($event->details['cause'])) {
                    $event_fields[$resource . '_status'] = $event->details['cause'];
                }
            }
        } catch (\GoCardlessPro\Core\Exception\InvalidSignatureException $e) {
            $this->Input->setErrors(
                ['internal' => ['response' => $e->getMessage()]]
            );

            return;
        }

        // Get the payment details
        $payment_details = null;

        try {
            if (isset($event_fields['payment_id'])) {
                $payment = $api->payments()->get($this->ifSet($event_fields['payment_id']));
                $payment_details = $payment->api_response->body->payments;
            }

            // Check if the payment it's associated to an active subscription
            if (isset($payment_details->links->subscription)) {
                $subscription = $api->subscriptions()->get($payment_details->links->subscription);
                $subscription_details = $subscription->api_response->body->subscriptions;
            }
        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
            $this->Input->setErrors(
                ['internal' => ['response' => $e->getMessage()]]
            );

            return;
        }

        // Log the API response
        $this->log(
            $this->ifSet($_SERVER['REQUEST_URI']),
            serialize($payment_details),
            'output',
            $this->getResponseStatus($payment)
        );

        // Capture the webhook status, or reject it if invalid
        $status = 'error';

        switch (strtolower($this->ifSet($event_fields['payment_status']))) {
            case 'payment_submitted':
                $status = 'approved';
                break;
            case 'payment_confirmed':
                $status = 'approved';
                break;
            case 'payment_paid_out':
                $status = 'approved';
                break;
            case 'customer_approval_denied':
                $status = 'declined';
                break;
            case 'direct_debit_not_enabled':
                $status = 'declined';
                break;
            case 'invalid_bank_details':
                $status = 'declined';
                break;
            case 'payment_cancelled':
                $status = 'void';
                break;
            case 'subscription_cancelled':
                $status = 'void';
                break;
            case 'payment_created':
                $status = 'pending';
                break;
            case 'subscription_created':
                $status = 'pending';
                break;
            case 'customer_approval_granted':
                $status = 'pending';
                break;
            case 'payment_retried':
                $status = 'pending';
                break;
            case 'chargeback_settled':
                $status = 'refunded';
                break;
            case 'refund_requested':
                $status = 'refunded';
                break;
        }

        // Get client id
        $client_id = $this->ifSet(
            $payment_details->metadata->client_id,
            $this->ifSet($subscription_details->metadata->client_id)
        );

        // Get invoices
        $invoices = $this->ifSet(
            $payment_details->metadata->invoices,
            $this->ifSet($subscription_details->metadata->invoices)
        );

        // Force 2-decimal places only
        $amount = $this->ifSet($payment_details->amount, 0) / 100;
        $amount = number_format($amount, 2, '.', '');

        return [
            'client_id' => $client_id,
            'amount' => $amount,
            'currency' => $this->ifSet($payment_details->currency),
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $this->ifSet($payment_details->id),
            'invoices' => $this->unserializeInvoices($invoices)
        ];
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    {
        // Load the GoCardless API
        $api = $this->getApi($this->meta['access_token'], $this->meta['dev_mode']);

        // Get the client id
        $client_id = $this->ifSet($get['client_id']);

        // Check if the payment is one time or a subscription
        $pay_type = 'onetime';

        if (isset($get['subscription_id'])) {
            $pay_type = 'subscribe';
        }

        // Get the payment details
        $payment_details = null;

        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($get), 'input', true);

        try {
            if ($pay_type == 'subscribe') {
                $payment = $api->subscriptions()->get($this->ifSet($get['subscription_id']));
                $payment_details = $payment->api_response->body->subscriptions;
                $payment_details->id = null;
            } elseif ($pay_type == 'onetime') {
                $payment = $api->payments()->get($this->ifSet($get['payment_id']));
                $payment_details = $payment->api_response->body->payments;
            }

            // Log the API response
            $this->log(
                $this->ifSet($_SERVER['REQUEST_URI']),
                serialize($payment_details),
                'output',
                $this->getResponseStatus($payment)
            );
        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
            $this->Input->setErrors(
                ['internal' => ['response' => $e->getMessage()]]
            );

            return;
        }

        // Force 2-decimal places only
        $amount = $this->ifSet($payment_details->amount, 0) / 100;
        $amount = number_format($amount, 2, '.', '');

        return [
            'client_id' => $client_id,
            'amount' => $amount,
            'currency' => $this->ifSet($payment_details->currency),
            'invoices' => $this->unserializeInvoices($this->ifSet($payment_details->metadata->invoices)),
            'status' => 'approved', // we wouldn't be here if it weren't, right?
            'transaction_id' => $this->ifSet($payment_details->id),
        ];
    }

    /**
     * Captures a previously authorized payment.
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction.
     * @param $amount The amount.
     * @param array $invoice_amounts
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * Void a payment or authorization.
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param string $notes Notes about the void that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function void($reference_id, $transaction_id, $notes = null)
    {
        // Load the GoCardless API
        $api = $this->getApi($this->meta['access_token'], $this->meta['dev_mode']);

        // Get the payment details
        try {
            $payment = $api->payments()->get($transaction_id);
            $payment_details = $payment->api_response->body->payments;

            // Log the API response
            $this->log(
                $this->ifSet($_SERVER['REQUEST_URI']),
                serialize($payment_details),
                'output',
                $this->getResponseStatus($payment)
            );

            // Check if the payment it's associated to an active subscription
            if (isset($payment_details->links->subscription)) {
                $subscription = $api->subscriptions()->get($payment_details->links->subscription);
                $subscription_details = $subscription->api_response->body->subscriptions;
            }

            // Log the API response
            $this->log(
                $this->ifSet($_SERVER['REQUEST_URI']),
                serialize($subscription_details),
                'output',
                $this->getResponseStatus($subscription)
            );

            // Cancel active subscription
            if (isset($subscription_details->id)) {
                $cancel = $api->subscriptions()->cancel($subscription_details->id);
            }

            return [
                'status' => 'void',
                'transaction_id' => $this->ifSet($transaction_id)
            ];
        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
            $this->Input->setErrors(
                ['internal' => ['response' => $e->getMessage()]]
            );

            return;
        }
    }

    /**
     * Refund a payment.
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param float $amount The amount to refund this card
     * @param string $notes Notes about the refund that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null)
    {
        // Load the GoCardless API
        $api = $this->getApi($this->meta['access_token'], $this->meta['dev_mode']);

        // Force 2-decimal places only
        $amount = number_format($amount, 2, '.', '');

        // Process the refund (only one-time payments can be refunded)
        if (substr($this->ifSet($transaction_id), 0, 2) == 'PM') {
            $params = [
                'params' => [
                    'amount' => $this->ifSet($amount) * 100,
                    'total_amount_confirmation' => $this->ifSet($amount) * 100,
                    'reference' => $this->ifSet($reference_id),
                    'links' => [
                        'payment' => $this->ifSet($transaction_id)
                    ]
                ]
            ];

            try {
                $refund = $api->refunds()->create($params);

                if (!$this->getResponseStatus($refund)) {
                    $this->Input->setErrors($this->getCommonError('general'));

                    return;
                }

                // Log the successful response
                $this->log(
                    $this->ifSet($_SERVER['REQUEST_URI']),
                    serialize($refund),
                    'output',
                    $this->getResponseStatus($refund)
                );

                return [
                    'status' => 'refunded',
                    'transaction_id' => $this->ifSet($refund->api_response->body->refunds->id),
                ];
            } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
                $this->Input->setErrors(
                    ['internal' => ['response' => $e->getMessage()]]
                );

                return;
            }
        } else {
            $this->Input->setErrors($this->getCommonError('unsupported'));
        }
    }

    /**
     * Serializes an array of invoice info into a string.
     *
     * @param array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
     */
    private function serializeInvoices(array $invoices)
    {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }

        return $str;
    }

    /**
     * Unserializes a string of invoice info into an array.
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @param mixed $str
     * @return array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function unserializeInvoices($str)
    {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }

        return $invoices;
    }

    /**
     * Initializes the GoCardless API and returns an instance of that object with the given account information set.
     *
     * @param string $access_token The account access token
     * @param string $dev_mode Post transactions to the GoCardless Sandbox environment
     * @return GoCardlessPro A GoCardlessPro instance
     */
    private function getApi($access_token, $dev_mode = 'false')
    {
        // Load the Guzzle autoloader
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'guzzle' . DS . 'autoloader.php');

        // Load the GoCardless API
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'gocardless' . DS . 'lib' . DS . 'loader.php');

        if ($dev_mode == 'true') {
            $environment = \GoCardlessPro\Environment::SANDBOX;
        } elseif ($dev_mode == 'false') {
            $environment = \GoCardlessPro\Environment::LIVE;
        }

        return new \GoCardlessPro\Client([
            'access_token' => $this->ifSet($access_token),
            'environment' => $environment
        ]);
    }

    /**
     * Generates a return url.
     *
     * @param string $return_url The return url, if no return url is provided, the current one will be used
     * @param array $params The GET parameters that will be added at the end of the url
     * @return string The formatted return url
     */
    private function generateReturnUrl($return_url = null, $params = [])
    {
        if (is_null($return_url)) {
            $return_url = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 's' : '')
                . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }

        if (!empty($params)) {
            $query = (strpos($return_url, '?') !== false ? '&' : '?') . http_build_query($params);
            $return_url = $return_url . $query;
        }

        return $return_url;
    }

    /**
     * Generates a redirect to the specified url.
     *
     * @param string $url The url to be redirected
     * @return bool True if the redirection was successful, false otherwise
     */
    private function redirectToUrl($url)
    {
        try {
            header('Location: ' . $url);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Returns the status of the API response.
     *
     * @param GoCardlessPro &$api_response The response of the api
     * @return bool True if the api response did not return any errors, false otherwise
     */
    private function getResponseStatus(&$api_response)
    {
        $status = false;

        if ($this->ifSet($api_response->api_response->status_code) >= 200
            && $this->ifSet($api_response->api_response->status_code) < 300
        ) {
            $status = true;
        }

        return $status;
    }
}
