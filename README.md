# octasync-magento
A Magento extension for integrating with Octahedron ERP software.

## Installation
Download the latest version and merge with your Magento installation.

## Configuration
In your Octahedron ERP application, navigate to **Utilities** > **Other** > **Applications** and create a new application.

**Note: the generated client ID and secret will be used to integrate Magento with your ERP software.**

While there, edit the webhook configuration for the new application and add your Magento webhook URL (e.g. *http<nolink>://yourmagentosite.com/pos/webhooks*). Also, subscribe to the  **Stock Change**, **Tax Change**, and **Category Change** events.

In your Magento admin site, navigate to **System** > **Configuration** > **Point of Sale** and enter the address of your Octahedron ERP (e.g. *yourcompany.onswim.com*).  Copy and paste the client ID and secret from above into the respective fields and hit save.  A success message will pop up if the connection was successful otherwise ensure all the fields have been entered correctly.

Finally, navigate to **Octahedron** > **Sync** and click **Perform Sync** to populate your Magento site with data pulled from your Octahedron ERP software.
