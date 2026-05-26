<!-- START include-tinymce -->

<?php

//Defaults
$tinymcemenubar = 'file edit view format';

$tinymceheight = '300';

$tinymcetoolbar = 'undo redo | bold italic underline strikethrough | fontselect fontsizeselect formatselect | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist checklist | forecolor backcolor casechange permanentpen formatpainter removeformat | pagebreak | charmap emoticons | fullscreen  preview save print | insertfile image media pageembed template link anchor codesample | a11ycheck ltr rtl | showcomments addcomment | code';

$tinymceplugins = 'code';

//Get valuses from preferences

$tinymcePrefs = [];
if (function_exists('cms_db_fetch_all')) {
    $tinymcePrefs = cms_db_fetch_all("SELECT `name`,`value` FROM `preferences` WHERE `name` LIKE :name", [':name' => 'prefTinyMCE%']);
} elseif (isset($conn) && $conn instanceof mysqli) {
    $querypref2 = mysqli_query($conn, "SELECT `name`,`value` FROM `preferences` WHERE `name` LIKE 'prefTinyMCE%'");
    if ($querypref2) {
        while ($row = mysqli_fetch_assoc($querypref2)) {
            $tinymcePrefs[] = $row;
        }
    }
}

foreach ($tinymcePrefs as $respref1) {

    if ($respref1["name"] == "prefTinyMCEMenu") {
        $tinymcemenubar = $respref1["value"];
    } 

    if ($respref1["name"] == "prefTinyMCEHeight") {
        $tinymceheight = $respref1["value"];
    } 
	

    if ($respref1["name"] == "prefTinyMCEToolbar") {
        $tinymcetoolbar = $respref1["value"];
    } 

    if ($respref1["name"] == "prefTinyMCEPlugins") {
        $tinymceplugins = $respref1["value"];
    } 

}

?>


<script type="text/javascript">
    tinymce.init({
        selector: 'textarea#tinymcetextarea',
        menubar: '<?php echo $tinymcemenubar; ?>',
        height: '<?php echo $tinymceheight ; ?>',
        toolbar: '<?php echo $tinymcetoolbar ; ?>',
        plugins: '<?php echo $tinymceplugins ; ?>',

        relative_urls: false,  // Disables converting absolute to relative URLs
        remove_script_host: false, // Keeps full URL with domain
        convert_urls: false,  // Prevents TinyMCE from modifying URLs
        document_base_url: "<?php echo $baseURL; ?>/" // Explicitly set base URL
    });
</script>

<!-- END include-tinymce -->