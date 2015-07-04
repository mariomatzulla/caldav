.. _Step3Htaccess:

==============================
Step 3: Htaccess
==============================

.. include:: ../../Includes.txt


Depending on your webserver, you need to activate a htaccess file in the extension root, to add a rewrite rule for authentication:

.. code-block:: html

	<IfModule mod_rewrite.c>
		RewriteEngine on
		RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
	</IfModule>

But first try if you can access without the _htaccess renamed to .htaccess.
