**Baseliner recruit assesment**
* Kurier.php is class with all needed logic to complete this assigment
* spring.php has removed API_KEY (I just can't push any form of credentials to repository)
* useCase.php use class Kurier to create shipment and get label for the shipment


**NOTES:**
* Param "Weight" is missing in provided dataset so all generated labels are temporary.
* Errors are just pass from SPRING API to user - I believe there is no need to wrap these errors in this stage of project
* Tested and optimized for CLI use and there is no feedback on errors LEVEL1 in browser fo user. But it's easy to do a some minor changes to code and add simple UI with HTML and JS.