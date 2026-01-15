# EHR Patient Feed Module

Creates records in near-real-time as events from EHRs (like Epic) are posted to specific feed URLs created & managed by this module. 
Currently, ED SOA events from Epic are supported, including 'External ID' to MRN resolution. Support for other feed types could easily be added.

*For advanced REDCap users
   
   **This is a basic overview of the EHR Patient Feed. More can be done when working with a DataCore 	
         developer. This is a technical module that may require extra assistance.
         
# To Use
1.	Enable module.
1.	Click on configure 
1.  Follow to drop-down menu to add the fields you want as the MRN and the Record Added Datetime fields.
1.	Click save.
1.	On the left-hand side of the REDCap column under External modules click EHR Patient Feed. 
1.  This will lead to the below image in the same REDCap window.
    * Here you will see the feeds that will automatically create records. 
1.  You can subscribe to feeds that have been created or you can create new feed.
    * This is the technical part that may need assistance
1.  Each feed must have a description, even when creating a feed, you will be asked to provide a description before being able to move on. 
