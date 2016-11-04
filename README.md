# octasync-magento
A Magento extension for integrating with Octahedron ERP software.

**Requirements:** Magento 1.9.x

## Installation
Download the latest version and merge with your Magento installation.

## Configuration
In your Octahedron ERP application, navigate to **Utilities** > **Other** > **Applications** and create a new application.

**Note: the generated client ID and secret will be used to integrate Magento with your ERP software.**

The plugin adds a new controller and action to your Magento site to accept webhook pushes and is accessible via a `POST` request to `/pos/webhooks`. To let your Octahedron ERP application know of the end point, edit the webhook configuration for the new application and add your full Magento webhook URL (e.g. *http://<i></i>yourmagentosite.com/pos/webhooks*). Also, subscribe to the  **Stock Change**, **Stock Picture Change**, and **Category Change** events.

In your Magento admin site, navigate to **System** > **Configuration** > **Point of Sale** and enter the address of your Octahedron ERP (e.g. *yourcompany.onswim.com*).  Copy and paste the client ID and secret from above into the respective fields and hit save.  A success message will pop up if the connection was successful otherwise ensure all the fields have been entered correctly.

Finally, navigate to **Octahedron** > **Sync** and click **Perform Sync** to populate your Magento site with data pulled from your Octahedron ERP software.

### Initial Stock Picture Import

After the sync is complete, you may wish to import the stock pictures from your Octahedron ERP application.  Head over to your Octahedron ERP application to download the pictures with corresponding CSV file via a URL similar to the following: **https://<i></i>yourcompany.onswim.com/stock/pictures/export** (replacing **yourcompany** with your actual subdomain).  You will need to extract the archive and upload all the pictures to the **media/import** directory of your Magento server (creating the **import** directory if it doesn't exist). The plugin adds a new import dataflow profile which can be used in concert with the CSV file to import the images and assign them to their products correctly. In your Magento admin site, navigate to **System** > **Import/Export** > **Dataflow - Profiles** and click on the new **Bulk Image Import** profile. Click the **Upload File** tab and select the CSV from the downloaded archive. Click **Save and Continue Edit** and then the **Run Profile** tab.  The uploaded CSV should appear in the dropdown. Select the file and click **Run Profile in Popup** where a new tab will open with the results of the picture import.
