<?php

GFForms::include_feed_addon_framework();

class GFEventRebels extends GFFeedAddOn
{
    protected $_version = GF_EVENTREBELS_VERSION;
    protected $_min_gravityforms_version = '1.9.12';
    protected $_slug = 'gravityformseventrebels';
    protected $_path = 'gravityformseventrebels/eventrebels.php';
    protected $_full_path = __FILE__;
    protected $_url = 'https://github.com/ilanco';
    protected $_title = 'Gravity Forms Event Rebels Add-On';
    protected $_short_title = 'Event Rebels';
    protected $_enable_rg_autoupgrade = true;
    protected $api = null;
    private static $_instance = null;

    /* Permissions */
    protected $_capabilities_settings_page = 'gravityforms_eventrebels';
    protected $_capabilities_form_settings = 'gravityforms_eventrebels';
    protected $_capabilities_uninstall = 'gravityforms_eventrebels_uninstall';

    /* Members plugin integration */
    protected $_capabilities = ['gravityforms_eventrebels', 'gravityforms_eventrebels_uninstall'];

    /**
     * @var string $custom_field_key The custom field key (label/name); used by get_full_address().
     */
    protected $custom_field_key = '';

    /**
     * Get instance of this class.
     *
     * @access public
     * @static
     * @return $_instance
     */
    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Register needed plugin hooks and PayPal delayed payment support.
     *
     * @access public
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->add_delayed_payment_support([
            'option_label' => esc_html__('Create Event Rebels object only when payment is received.', 'gravityformseventrebels')
        ]);
    }

    /**
     * Add hook for Javascript analytics tracking.
     *
     * @access public
     * @return void
     */
    public function init_frontend()
    {
        parent::init_frontend();
    }

    /**
     * Register needed styles.
     *
     * @access public
     * @return array $styles
     */
    public function styles()
    {
        $styles = [
            [
                'handle' => 'gform_eventrebels_form_settings_css',
                'src' => $this->get_base_url() . '/css/form_settings.css',
                'version' => $this->_version,
                'enqueue' => [
                    ['admin_page' => ['form_settings']],
                ]
            ]
        ];

        return array_merge(parent::styles(), $styles);
    }

    /**
     * Setup plugin settings fields.
     *
     * @access public
     * @return array
     */
    public function plugin_settings_fields()
    {
        return [
            [
                'title' => '',
                'description' => $this->plugin_settings_description(),
                'fields' => [
                    [
                        'name' => 'accountApiToken',
                        'label' => __('Account API Token', 'gravityformseventrebels'),
                        'type' => 'text',
                        'class' => 'medium',
                        'feedback_callback' => [$this, 'initialize_api']
                    ],
                    [
                        'name' => 'activityToken',
                        'label' => __('Activity Token', 'gravityformseventrebels'),
                        'type' => 'text',
                        'class' => 'medium',
                        'feedback_callback' => [$this, 'initialize_api']
                    ]
                ]
            ]
        ];
    }

    /**
     * Prepare plugin settings description.
     *
     * @access public
     * @return string $description
     */
    public function plugin_settings_description()
    {
        $description  = '<p>';
        $description .= sprintf(
            __('Event Rebels provides a complete suite of software for meeting and event planners. Use Gravity Forms to collect customer information and automatically add them to your Event Rebels account.', 'gravityformseventrebels')
        );
        $description .= '</p>';

        if (!$this->initialize_api()) {
            $description .= '<p>';
            $description .= __('Gravity Forms Event Rebels Add-On requires your Account API Token and Activity Token. Contact Event Rebels for more information.', 'gravityformseventrebels');
            $description .= '</p>';

        }

        return $description;
    }

    /**
     * Setup fields for feed settings.
     *
     * @access public
     * @return array
     */
    public function feed_settings_fields()
    {
        /* Build base fields array. */
        $base_fields = [
            'title'  => '',
            'fields' => [
                [
                    'name' => 'feedName',
                    'label' => __('Feed Name', 'gravityformseventrebels'),
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $this->get_default_feed_name(),
                    'tooltip' => '<h6>'. __('Name', 'gravityformseventrebels') .'</h6>' . __('Enter a feed name to uniquely identify this setup.', 'gravityformseventrebels')
                ],
                [
                    'name' => 'action',
                    'label' => __('Action', 'gravityformseventrebels'),
                    'type' => 'checkbox',
                    'required' => true,
                    'onclick' => "jQuery(this).parents('form').submit();",
                    'choices' => [
                        [
                            'name' => 'registerContact',
                            'label' => __('Register Contact', 'gravityformseventrebels'),
                            'icon' => 'fa-user',
                        ]
                    ]
                ],
                [
                    'name' => 'fee',
                    'dependency' => ['field' => 'registerContact', 'values' => ('1')],
                    'label' => __('Fee', 'gravityformseventrebels'),
                    'type' => 'select',
                    'required' => true,
                    'choices' => $this->get_fee_choices()
                ]
            ]
        ];

        /* Build contact fields array. */
        $contact_fields = [
            'title'  => __('Contact Details', 'gravityformseventrebels'),
            'dependency' => ['field' => 'registerContact', 'values' => ('1')],
            'fields' => [
                [
                    'name' => 'contactStandardFields',
                    'label' => __('Map Fields', 'gravityformseventrebels'),
                    'type' => 'field_map',
                    'field_map' => $this->standard_fields_for_feed_mapping(),
                    'tooltip' => '<h6>'. __('Map Fields', 'gravityformseventrebels') .'</h6>' . __('Select which Gravity Form fields pair with their respective Event Rebels fields.', 'gravityformseventrebels')
                ],
                [
                    'name' => 'contactCustomFields',
                    'label' => '',
                    'type' => 'dynamic_field_map',
                    'field_map' => $this->custom_fields_for_feed_mapping(),
                ]
            ]
        ];

        /* Build conditional logic fields array. */
        $conditional_fields = [
            'title' => __('Feed Conditional Logic', 'gravityformseventrebels'),
            'dependency' => [$this, 'show_conditional_logic_field'],
            'fields' => [
                [
                    'name' => 'feedCondition',
                    'type' => 'feed_condition',
                    'label' => __( 'Conditional Logic', 'gravityformseventrebels' ),
                    'checkbox_label' => __( 'Enable', 'gravityformseventrebels' ),
                    'instructions' => __( 'Export to Event Rebels if', 'gravityformseventrebels' ),
                    'tooltip' => '<h6>' . __( 'Conditional Logic', 'gravityformseventrebels' ) . '</h6>' . __( 'When conditional logic is enabled, form submissions will only be exported to Event Rebels when the condition is met. When disabled, all form submissions will be posted.', 'gravityformseventrebels' )
                ]
            ]
        ];

        return [$base_fields, $contact_fields, $conditional_fields];
    }

    /**
     * Set custom dependency for conditional logic.
     *
     * @access public
     * @return bool
     */
    public function show_conditional_logic_field()
    {
        /* Get current feed. */
        $feed = $this->get_current_feed();

        /* Get posted settings. */
        $posted_settings = $this->get_posted_settings();

        /* Show if an action is chosen */
        if (rgar($posted_settings, 'registerContact') == '1' || rgars($feed, 'meta/registerContact') == '1') {
            return true;
        }

        return false;
    }

    /**
     * Prepare standard fields for feed field mapping.
     *
     * @access public
     * @return array
     */
    public function standard_fields_for_feed_mapping()
    {
        return [
            [
                'name' => 'first_name',
                'label' => __('First Name', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['name', 'text', 'hidden'],
                'default_value' => $this->get_first_field_by_type('name', 3)
            ],
            [
                'name' => 'last_name',
                'label' => __('Last Name', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['name', 'text', 'hidden'],
                'default_value' => $this->get_first_field_by_type('name', 6)
            ],
            [
                'name' => 'email_address',
                'label' => __('Email Address', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['email', 'hidden'],
                'default_value' => $this->get_first_field_by_type('email')
            ],
            [
                'name' => 'phone_number',
                'label' => __('Phone Number', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['phone', 'hidden'],
                'default_value' => $this->get_first_field_by_type('phone')
            ],
            [
                'name' => 'address_street1',
                'label' => __('Address Street 1', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['address', 'text', 'hidden'],
                'default_value' => $this->get_first_field_by_type('address', 1)
            ],
            [
                'name' => 'address_street2',
                'label' => __('Address Street 2', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['address', 'text', 'hidden'],
                'default_value' => $this->get_first_field_by_type('address', 2)
            ],
            [
                'name' => 'address_city',
                'label' => __('Address City', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['address', 'text', 'hidden'],
                'default_value' => $this->get_first_field_by_type('address', 3)
            ],
            [
                'name' => 'address_state',
                'label' => __('Address State', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['address', 'text', 'hidden'],
                'default_value' => $this->get_first_field_by_type('address', 4)
            ],
            [
                'name' => 'address_zip',
                'label' => __('Address Zip', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['address', 'text', 'hidden'],
                'default_value' => $this->get_first_field_by_type('address', 5)
            ],
            [
                'name' => 'address_country',
                'label' => __('Address Country', 'gravityformseventrebels'),
                'required' => true,
                'field_type' => ['address', 'text', 'hidden'],
                'default_value' => $this->get_first_field_by_type('address', 6)
            ]
        ];
    }

    /**
     * Prepare contact and custom fields for feed field mapping.
     *
     * @access public
     * @return array
     */
    public function custom_fields_for_feed_mapping()
    {
        return [
            [
                'label' => __('Choose a Field', 'gravityformseventrebels')
            ],
            [
                'value' => 'gf_custom',
                'label' => __('Add a Custom Field', 'gravityformseventrebels')
            ]
        ];
    }

    /**
     * Set feed creation control.
     *
     * @access public
     * @return bool
     */
    public function can_create_feed()
    {
        return $this->initialize_api();
    }

    /**
     * Setup columns for feed list table.
     *
     * @access public
     * @return array
     */
    public function feed_list_columns()
    {
        return [
            'feedName' => __('Name', 'gravityformseventrebels'),
            'action' => __('Action', 'gravityformseventrebels'),
        ];
    }

    /**
     * Get value for action feed list column.
     *
     * @access public
     * @param array $feed
     * @return string $action
     */
    public function get_column_value_action($feed)
    {
        if (rgars($feed, 'meta/registerContact') == '1') {
            return esc_html__('Register New Contact', 'gravityformseventrebels');
        }
    }

    /**
     * Process feed.
     *
     * @access public
     * @param array $feed
     * @param array $entry
     * @param array $form
     * @return void
     */
    public function process_feed($feed, $entry, $form)
    {
        $this->log_debug(__METHOD__ . '(): Processing feed.');

        /* If API instance is not initialized, exit. */
        if (!$this->initialize_api()) {
            $this->add_feed_error(esc_html__('Feed was not processed because API was not initialized.', 'gravityformsicontact'), $feed, $entry, $form);

            return;
        }

        /* Register contact? */
        if (rgars($feed, 'meta/registerContact') == 1) {
            $contact = $this->register_contact($feed, $entry, $form);
        }
    }

    /**
     * Register contact.
     *
     * @access public
     * @param array $feed
     * @param array $entry
     * @param array $form
     * @return array $contact
     */
    public function register_contact($feed, $entry, $form)
    {
        $this->log_debug(__METHOD__ . '(): Registering contact.');

        /* Get fee item id. */
        $fee_item_id = rgars($feed, 'meta/fee');

        /* Setup mapped fields array. */
        $contact_standard_fields = $this->get_field_map_fields($feed, 'contactStandardFields');
        $contact_custom_fields = $this->get_dynamic_field_map_fields($feed, 'contactCustomFields');

        /* Setup base fields. */
        $first_name = $this->get_field_value($form, $entry, $contact_standard_fields['first_name']);
        $last_name = $this->get_field_value($form, $entry, $contact_standard_fields['last_name']);
        $email_address = $this->get_field_value($form, $entry, $contact_standard_fields['email_address']);
        $phone_number = $this->get_field_value($form, $entry, $contact_standard_fields['phone_number']);
        $address_street1 = $this->get_field_value($form, $entry, $contact_standard_fields['address_street1']);
        $address_street2 = $this->get_field_value($form, $entry, $contact_standard_fields['address_street2']);
        $address_city = $this->get_field_value($form, $entry, $contact_standard_fields['address_city']);
        $address_state = $this->get_field_value($form, $entry, $contact_standard_fields['address_state']);
        $address_zip  = $this->get_field_value($form, $entry, $contact_standard_fields['address_zip']);
        $address_country = $this->get_field_value($form, $entry, $contact_standard_fields['address_country']);

        /* If the name is empty, exit. */
        if (rgblank($first_name) || rgblank($last_name)) {
            $this->add_feed_error(esc_html__('Contact could not be created as first and/or last name were not provided.', 'gravityformseventrebels'), $feed, $entry, $form);

            return null;
        }

        /* If the email address is empty, exit. */
        if (GFCommon::is_invalid_or_empty_email($email_address)) {
            $this->add_feed_error(esc_html__('Contact could not be created as email address was not provided.', 'gravityformseventrebels'), $feed, $entry, $form);

            return null;
        }

        /* Build base contact. */
        $contact = [
            'FeeItemID' => $fee_item_id,
            'FirstName' => $first_name,
            'LastName' => $last_name,
            'Email' => $email_address,
            'PhoneNumber' => $phone_number,
            'Address1' => $address_street1,
            'Address2' => $address_street2,
            'City' => $address_city,
            'State' => $address_state,
            'ZipCode' => $address_zip,
            'Country' => $address_country,
        ];

        /* Add custom field data. */
        foreach ($contact_custom_fields as $field_key => $field_id) {
            /* Get the field value. */
            $this->custom_field_key = $field_key;
            $field_value = $this->get_field_value($form, $entry, $field_id);

            /* If the field value is empty, skip this field. */
            if (rgblank($field_value)) {
                continue;
            }

            $contact = $this->add_contact_property($contact, $field_key, $field_value);
        }

        $this->log_debug(__METHOD__ . '(): Registering contact: ' . print_r($contact, true));

        try {
            /* Register contact. */
            $contact = $this->api->register_contact($contact);

            /* Save registrant ID to entry. */
            gform_update_meta($entry['id'], 'eventrebels_registrant_id', $contact['RegistrantID']);
            /* Save billing ID to entry. */
            gform_update_meta($entry['id'], 'eventrebels_billing_id', $contact['BillingID']);

            /* Log that contact was created. */
            $this->log_debug(__METHOD__ . '(): Contact #' . $contact['RegistrantID'] . ' created.');

        } catch (Exception $e) {
            $this->add_feed_error(sprintf(esc_html__('Contact could not be created. %s', 'gravityformseventrebels'), $e->getMessage()), $feed, $entry, $form);

            return null;
        }

        return $contact;
    }

    /**
     * Add property to contact object.
     *
     * @access public
     * @param array $contact
     * @param string $field_key
     * @param string $field_value
     * @param bool $replace (default: false)
     * @return array $contact
     */
    public function add_contact_property($contact, $field_key, $field_value, $replace = false)
    {
        /* Add property object to properties array. */
        $contact[$field_key] = $field_value;

        return $contact;
    }

    /**
     * Initializes Event Rebels API if credentials are valid.
     *
     * @access public
     * @return bool
     */
    public function initialize_api()
    {
        if (!is_null($this->api)) {
            return true;
        }

        /* Load the Event Rebels API library. */
        if (!class_exists('Eventrebels_API')) {
            require_once 'includes/class-eventrebels-api.php';
        }

        /* Get the plugin settings */
        $settings = $this->get_plugin_settings();

        /* If any of the account information fields are empty, return null. */
        if (rgblank($settings['accountApiToken']) || rgblank($settings['activityToken'])) {
            return null;
        }

        $this->log_debug(__METHOD__ . "(): Validating API info for {$settings['accountApiToken']} / {$settings['activityToken']}.");

        $eventrebels = new Eventrebels_API($settings['accountApiToken'], $settings['activityToken']);

        try {
            /* Run API test. */
            $eventrebels->get_fees();

            /* Log that test passed. */
            $this->log_debug(__METHOD__ . '(): API credentials are valid.');

            /* Assign Event Rebels object to the class. */
            $this->api = $eventrebels;

            return true;
        } catch (Exception $e) {
            /* Log that test failed. */
            $this->log_error(__METHOD__ . '(): API credentials are invalid; '. $e->getMessage());

            /* Assign null to the class. */
            $this->api = null;

            return false;
        }
    }

    /**
     * Returns the combined value of the specified Address field.
     *
     * @param array $entry
     * @param string $field_id
     *
     * @return string
     */
    public function get_full_address($entry, $field_id)
    {
        $street_value = str_replace('  ', ' ', trim(rgar($entry, $field_id . '.1')));
        $street2_value = str_replace('  ', ' ', trim(rgar($entry, $field_id . '.2')));
        $city_value = str_replace('  ', ' ', trim(rgar($entry, $field_id . '.3')));
        $state_value = str_replace('  ', ' ', trim(rgar($entry, $field_id . '.4')));
        $zip_value = trim(rgar($entry, $field_id . '.5'));
        $country_value = trim(rgar($entry, $field_id . '.6'));

        $address = $street_value;
        $address .= !empty($street_value) && !empty($street2_value) ? "  $street2_value" : $street2_value;

        if (strpos($this->custom_field_key, 'address_') === 0) {
            $address_array = [
                'address' => $address,
                'city' => $city_value,
                'state' => $state_value,
                'zip' => $zip_value,
                'country' => $country_value,
            ];

            return json_encode($address_array);
        } else {
            $address .= !empty($address) && (!empty($city_value) || !empty($state_value)) ? ", $city_value," : $city_value;
            $address .= !empty($address) && !empty($city_value) && !empty($state_value) ? "  $state_value" : $state_value;
            $address .= !empty($address) && !empty($zip_value) ? "  $zip_value," : $zip_value;
            $address .= !empty($address) && !empty($country_value) ? "  $country_value" : $country_value;

            return $address;
        }
    }

    protected function get_fee_choices()
    {
        if (!$this->initialize_api()) {
            return false;
        }

        $fees = $this->api->get_fees();

        $result = [[
            'label' => 'Choose Fee',
            'value' => ''
        ]];
        foreach ($fees as $fee) {
            $result[] = [
                'label' => $fee['FeeName'],
                'value' => $fee['FeeItemID']
            ];
        }

        return $result;
    }
}
