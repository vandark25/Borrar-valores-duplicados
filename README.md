**Take backup of database before running this command**

Install the module and execute following command to delete all unused options for a specific **Product attribute**.

*php bin/magento rlt:delete-attribute-options --attributeCode=color*

here color is the attribute code for which we want to delete unused options.

**Limitation**
*Can only be used on product attributes.
*Limited to dropdown attributes only.