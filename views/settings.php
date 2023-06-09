<h1>Privytics Settings</h1>

<h2>Process live data</h2>

<form method="post" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
    <input type="hidden" name="form_id" value="process_data">
    <p>This site or product includes IP2Location LITE data available from <a href="https://lite.ip2location.com">https://lite.ip2location.com</a>.</p>
    <p>IP address information is only stored in live data. After processing completed, the live data will be deleted. 
       The IP address will only be processed locally to get information about the country of origin.
    </p>
    <input type="submit" value="Process live data now">
</form>

<h2>Internal functions - do not use</h2>

<form method="post" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">    
    <input type="hidden" name="form_id" value="internal">
    <input type="submit" value="DB Updates">
</form>

<?php

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["form_id"] == "process_data") {
    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-data-access.php');
    $data = new PrivyticsDataAccess();
    $data->processLiveData();
}
elseif (isset($_POST["form_id"]) && $_POST["form_id"] == "internal") {
    doInternal();
}

function doInternal()
{
    require_once(ABSPATH . 'wp-content/plugins/privytics/classes/class-privytics-data-access.php');
    $data = new PrivyticsDataAccess();
    $data->createOrUpdateDatabaseSchema();
}




?>