<?php
if (!check_bitrix_sessid()) {
    return;
}
echo CAdminMessage::ShowNote(GetMessage("LS_FARPOST_MODULE_INSTALLED"));
?>
<form action="<?= $APPLICATION->GetCurPage(); ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID; ?>">
    <input type="submit" name="" value="<?= GetMessage("MOD_BACK"); ?>">
</form>
