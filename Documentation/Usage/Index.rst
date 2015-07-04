.. _Usage:

=================
Usage
=================

.. include:: ../Includes.txt

Now try and check if you can access the webinterface:

www.your-domain.com/typo3conf/ext/caldav/caldav.php/

You should see: “Index for /” and two links to select: “principals” and “calendars”.
Now try to connect your CalDAV client to your TYPO3 calendar. The URL should look like this:
For mozilla thunderbird extension sunbird or lightning:

www.your-domain.com/typo3conf/ext/caldav/caldav.php/calendars/{username}/{calendar title}/

For your iphone:

www.your-domain.com/typo3conf/ext/caldav/caldav.php/principals/{username}/


