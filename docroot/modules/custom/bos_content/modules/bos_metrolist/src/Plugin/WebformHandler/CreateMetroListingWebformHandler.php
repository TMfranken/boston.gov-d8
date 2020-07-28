<?php

namespace Drupal\bos_metrolist\Plugin\WebformHandler;

use Drupal\salesforce\Exception;
use Drupal\salesforce\SelectQuery;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use function PHPUnit\Framework\arrayHasKey;

/**
 * Create a new node entity from a webform submission.
 *
 * @WebformHandler(
 *   id = "Create a MetroList Listing",
 *   label = @Translation("Create a MetroList Listing"),
 *   category = "MetroList",
 *   description = @Translation("Create a MetroList Listing on SF via a Webform."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class CreateMetroListingWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function client() {
    return \Drupal::service('salesforce.client');
  }

  /**
   * {@inheritdoc}
   */
  public function authMan() {
    return \Drupal::service('plugin.manager.salesforce.auth_providers');
  }

  /**
   * {@inheritdoc}
   */
  public function getSalesforceUrl() {
    return $this->authMan()->getProvider()->getInstanceUrl() . '/' . $this->salesforce_id->value;
  }

  /**
   * Lookup a SF Contact by Email.
   *
   * @param string $email
   *   Value of Contact email address.
   *
   * @return \Drupal\salesforce\SObject
   *   Object of the requested Salesforce object, Contact that matches the email.
   */
  public function getContactByEmail($email = '') {

    $contactSFObject = $this->client()->objectReadbyExternalId('Contact', 'Email', $email);

    return $contactSFObject;
  }

  /**
   * Add or update a SF Contact.
   *
   * @param array $developmentData
   *   Submission Data.
   *
   * @return mixed
   *   Return SFID of Contact or false
   */
  public function addContact(array $developmentData) {

    $contactEmail = $developmentData['contact_email'] ?? NULL;
    $contactName = $developmentData['contact_name'] ?? NULL;
    $contactPhone = $developmentData['contact_phone'] ?? NULL;
    $contactAddress = $developmentData['contact_address'] ?? [];

    $fieldData = [
    // @TODO: Make Config?, Hardcoded the SFID to `DND Contacts`
      'AccountId' => $this->addAccount($developmentData),
      'Email' => $contactEmail,
    ];

    if ($contactPhone) {
      $fieldData['Phone'] = $contactPhone;
    }

    $contactName = explode(' ', $contactName);
    if (isset($contactName[1])) {
      $contactFirstName = $contactName[0];
      $fieldData['FirstName'] = $contactFirstName;
      $contactLastName = $contactName[1];
      $fieldData['LastName'] = $contactLastName;
    }
    else {
      $contactFirstName = NULL;
      $contactLastName = $contactName[0];
      $fieldData['LastName'] = $contactLastName;
    }

    if (!empty($contactAddress)) {
      $fieldData['MailingStreet'] = $contactAddress['address'];
      $fieldData['MailingCity'] = $contactAddress['city'];
      $fieldData['MailingState'] = $contactAddress['state_province'];
      $fieldData['MailingPostalCode'] = $contactAddress['postal_code'];
    }

    try {
      $contactQuery = new SelectQuery('Contact');
      if ($contactFirstName) {
        $contactQuery->addCondition('FirstName', "'$contactFirstName'");
      }
      $contactQuery->addCondition('LastName', "'$contactLastName'");
      $contactQuery->addCondition('Email', "'$contactEmail'");
      $contactQuery->fields = ['Id', 'Name', 'Email'];

      $existingContact = reset($this->client()->query($contactQuery)->records()) ?? NULL;

    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }

    if ($existingContact) {
      return (string) $existingContact->id();
    }
    else {
      try {
        // Return (string) $this->client()->objectUpsert('Contact', 'Email', $contactEmail, $fieldData);.
        return (string) $this->client()->objectCreate('Contact', $fieldData);
      }
      catch (Exception $exception) {
        \Drupal::logger('bos_metrolist')->error($exception->getMessage());
        return FALSE;
      }
    }
  }

  /**
   * Add or update a SF Account.
   *
   * @param array $developmentData
   *   Submission Data.
   *
   * @return mixed
   *   Return SFID or false
   */
  public function addAccount(array $developmentData) {

    try {
      $accountQuery = new SelectQuery('Account');
      $company = $developmentData['contact_company'];
      $accountQuery->addCondition('Name', "'$company'");
      $accountQuery->addCondition('Type', "'Property Manager'");
      $accountQuery->addCondition('Division__c', "'DND'");
      // @TODO: hardcoded to SFID for Account Record Type: "Vendor"
      $accountQuery->addCondition('RecordTypeId', "'012C0000000I0hCIAS'");
      $accountQuery->fields = ['Id', 'Name', 'Type'];

      $existingAccount = reset($this->client()->query($accountQuery)->records()) ?? NULL;

    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }

    if ($existingAccount) {
      // Just return the SFID of the account, no need to update anything....
      return (string) $existingAccount->id();
    }
    else {
      // Create a new Account in SF.
      $fieldData = [
        'Name' => $developmentData['contact_company'],
        'Business_Legal_Name__c' => $developmentData['contact_company'],
        'Type' => 'Property Manager',
        'Division__c' => 'DND',
      // @TODO: hardcoded to SFID for Account Record Type: "Vendor"
        'RecordTypeId' => '012C0000000I0hCIAS',
      ];

      try {
        return (string) $this->client()->objectCreate('Account', $fieldData);
      }
      catch (Exception $exception) {
        \Drupal::logger('bos_metrolist')->error($exception->getMessage());
        return FALSE;
      }
    }

  }

  /**
   * Add or update a SF Development.
   *
   * @param string $developmentName
   *   Development Name.
   * @param array $developmentData
   *   Submission Data.
   * @param string $contactId
   *   Contact ID.
   *
   * @return mixed
   *   Return SFID or false
   */
  public function addDevelopment(string $developmentName, array $developmentData, string $contactId) {

    $fieldData = [
      'Name' => $developmentName,
      'Region__c' => !empty($developmentData['region']) ? $developmentData['region'] : 'Boston',
      'Street_Address__c' => $developmentData['street_address'] ?? '',
      'City__c' => !empty($developmentData['city']) ? $developmentData['city'] : 'Boston',
      'ZIP_Code__c' => $developmentData['zip_code'] ?? '',
      'Wheelchair_Access__c' => empty($developmentData['wheelchair_accessible']) ? FALSE : TRUE,
      'Listing_Contact_Company__c' => $developmentData['contact_company'] ?? NULL,
    ];

    if (isset($contactId)) {
      $fieldData['Listing_Contact__c'] = $contactId;

      // @TODO: change to Man_Comp_Contact__c and set the Account on the Contact and not the Development.
      $fieldData['Management_Company_Contact__c'] = $contactId;
    }

    if (isset($developmentData['neighborhood'])) {
      $fieldData['Neighborhood__c'] = !empty($developmentData['neighborhood']) ? $developmentData['neighborhood'] : NULL;
    }

    if (isset($developmentData['utilities_included'])) {
      $fieldData['Utilities_included__c'] = !empty($developmentData['utilities_included']) ? implode(';', $developmentData['utilities_included']) : NULL;
    }

    if (isset($developmentData['upfront_fees'])) {
      $fieldData['Due_at_signing__c'] = !empty($developmentData['upfront_fees']) ? implode(';', $developmentData['upfront_fees']) : NULL;
    }

    if (isset($developmentData['utilities_included'])) {
      $fieldData['Features__c'] = !empty($developmentData['amenities_features']) ? implode(';', $developmentData['amenities_features']) : NULL;
    }

    if (empty($developmentData['same_as_above_contact_info'])) {
      if (!empty($developmentData['public_contact_address'])) {
        $addr = $developmentData['public_contact_address'];
        $fieldData['Public_Contact_Address__c'] = $addr['address'] . "\r\n" . $addr['city'] . ", " . $addr['state_province'] . " " . $addr['postal_code'];
      }
      $fieldData['Public_Contact_Email__c'] = $developmentData['public_contact_email'];
      $fieldData['Public_Contact_Name__c'] = $developmentData['public_contact_name'];
      $fieldData['Public_Contact_Phone__c'] = $developmentData['public_contact_phone'];
    }
    else {
      if (!empty($developmentData['contact_address'])) {
        $addr = $developmentData['contact_address'];
        $fieldData['Public_Contact_Address__c'] = $addr['address'] . "\r\n" . $addr['city'] . ", " . $addr['state_province'] . " " . $addr['postal_code'];
      }
      $fieldData['Public_Contact_Email__c'] = $developmentData['contact_email'];
      $fieldData['Public_Contact_Name__c'] = $developmentData['contact_name'];
      $fieldData['Public_Contact_Phone__c'] = $developmentData['contact_phone'];
    }

    try {
      return (string) $this->client()->objectUpsert('Development__c', 'Name', $developmentName, $fieldData);
    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }
  }

  /**
   * Add or update a SF Units.
   *
   * @param array $developmentData
   *   Submission Data.
   * @param string $developmentId
   *   Development ID.
   *
   * @return mixed
   *   Return SFID or false
   */
  public function addUnits(array $developmentData, string $developmentId) {

    $units = $developmentData['units'];
    try {

      $unitsQuery = new SelectQuery('Development_Unit__c');
      // $unitsQuery->addCondition('Development_new__c', "'a093F000006AFZU'");
      $unitsQuery->addCondition('Development_new__c', "'$developmentId'");
      $unitsQuery->fields = ['Id', 'Name'];
      $unitsResults = $this->client()->query($unitsQuery)->records() ?? NULL;

    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }

    if (empty($unitsResults)) {
      foreach ($units as $unitGroup) {

        for ($unitNumber = 1; $unitNumber <= $unitGroup['unit_count']; $unitNumber++) {

          $unitName = $developmentData['street_address'] . ' Unit #' . $unitNumber;

          // @TODO: Change out the values for some of these by updating the options values in the webform configs to match SF.
          $fieldData = [
            'Name' => $unitName,
            'Development_new__c' => $developmentId,
            'Availability_Status__c' => 'Pending',
            'Income_Restricted_new__c' => $developmentData['units_income_restricted'] ?? 'Yes',
            'Availability_Type__c' => $developmentData['available_how'] == 'first_come_first_serve' ? 'First come, first served' : 'Lottery',
            'User_Guide_Type__c' => $developmentData['available_how'] == 'first_come_first_serve' ? 'First come, first served' : 'Lottery',
            'Occupancy_Type__c' => $developmentData['type_of_listing'] == 'rental' ? 'Rent' : 'Own',
          // @TODO: Need to add this to the Listing Form somehow for "% of Income"
            'Rent_Type__c' => 'Fixed $',
            'Income_Eligibility_AMI_Threshold__c' => isset($unitGroup['ami']) ? $unitGroup['ami'] . '% AMI' : 'N/A',
            'Number_of_Bedrooms__c' => isset($unitGroup['bedrooms']) ? (double) $unitGroup['bedrooms'] : 0.0,
            'Rent_or_Sale_Price__c' => isset($unitGroup['price']) ? (double) filter_var($unitGroup['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0.0,
            'ADA_V__c' => empty($unitGroup['ada_v']) ? FALSE : TRUE,
            'ADA_H__c' => empty($unitGroup['ada_h']) ? FALSE : TRUE,
            'ADA_M__c' => empty($unitGroup['ada_m']) ? FALSE : TRUE,
            'Waitlist_Open__c' => $developmentData['waitlist_open'] == 'No' || empty($developmentData['waitlist_open']) ? FALSE : TRUE,
          ];

          if (isset($unitGroup['bathrooms'])) {
            $fieldData['Number_of_Bathrooms__c'] = isset($unitGroup['bathrooms']) ? (double) $unitGroup['bathrooms'] : 0.0;
          }

          if (isset($unitGroup['minimum_income_threshold'])) {
            $fieldData['Minimum_Income_Threshold__c'] = !empty($unitGroup['minimum_income_threshold']) ? (double) filter_var($unitGroup['minimum_income_threshold'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0.0;
          }

          if (isset($developmentData['posted_to_metrolist_date'])) {
            $fieldData['Requested_Publish_Date__c'] = $developmentData['posted_to_metrolist_date'];
          }

          if (isset($developmentData['application_deadline_datetime'])) {
            $fieldData['Lottery_Application_Deadline__c'] = $developmentData['application_deadline_datetime'];
          }

          if (isset($developmentData['website_link'])) {
            $fieldData['Lottery_Application_Website__c'] = $developmentData['website_link'] ?? NULL;
          }

          try {
            $this->client()->objectUpsert('Development_Unit__c', 'Name', $unitName, $fieldData);
          }
          catch (Exception $exception) {
            \Drupal::logger('bos_metrolist')->error($exception->getMessage());
            return FALSE;
          }

        }

      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {

    $fieldData = $webform_submission->getData();

    if ($webform_submission->isCompleted()) {
      $contactId = $this->addContact($fieldData);

      if ($contactId) {
        $developmentId = $this->addDevelopment($fieldData['property_name'], $fieldData, $contactId);

        if ($developmentId) {
          $this->addUnits($fieldData, $developmentId);
        }
      }
    }

    // TODO: Change the autogenerated stub.
    parent::postSave($webform_submission, $update);
  }

}