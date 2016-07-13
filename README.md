# Contact Salesforce

Contract salesforce is a module that allows administrators to configure contact
forms to send results to salesforce.com using the web-to-lead approach. The UI
has a simple implementation to map entity fields to salesforce fields. Plans in
the future is to use the salesforce.com API to retrieve field information.

## Installation

1. Install and enable the module.
2. Navigate to the contact salesforce configuration (/admin/config/services/contact_salesforce).
You'll need to input the organization ID for your salesforce account. Also
defined any salesforce fields that entity fields can be mapped to from the
contact form edit type.
3. Then navigate to any contact form type and configure it to send results to
salesforce.com.
