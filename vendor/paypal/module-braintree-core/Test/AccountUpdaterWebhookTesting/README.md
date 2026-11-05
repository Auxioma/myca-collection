# Braintree Account Updater Testing

* Create customer account and vault some cards using Braintree,
* Create a CSV with the following headers:  
  payment\_method\_token,customer\_id,update\_type,bin,new\_last\_4,old\_last\_4,new\_expiration,card\_updated\_at,source,new\_card\_type
* Add a row for each card you want to update and complete at minimum:
    * payment\_method\_token (Look in vault\_payment\_token table for the card you wish to update testing, this will be used as the primary key to locate the vaulted card)
    * old\_last\_4 (This is used to ensure we only update cards that have changed, i.e. when we have loaded the vaulted token using payment\_method\_token, first check old\_last\_4 matches the card)
    * new\_last\_4 (This will be updated against the token)
    * new\_expiration (This is the new expiry that will be shown for the updated card in the format mm/yy),
    * new\_card\_type (One of: MasterCard, Visa, Discover)
* Place the CSV in a location accessible to your server,
* Open the test script and update the following:
    * environment
    * merchantId
    * publicKey
    * privateKey
    * webhookUrl
    * csvUrl
* Run `php fore-webhook.php`. If everything worked you will get a `202` response code,
* Run the command `bin/magento queue:consumers:start braintree.account.update.consumer` or allow your message-queue to process the jobs as normal if already configured,
* Go to the account area of the QA account being tested. The last 4 and expiry date of the vaulted card should now be the ones specified in the CSV file.
