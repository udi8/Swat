<?php
/**
 * SWAT Plugin - Profile rights tab
 */
class PluginSwatProfile extends CommonDBTM {

    static $rightname = 'profile';

    public static function getTypeName($nb = 0) {
        return 'SWAT';
    }

    // Instance method - as required by CommonGLPI in GLPI 10.0.6
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item instanceof Profile) {
            return 'SWAT';
        }
        return '';
    }

    // Static method - as required by CommonGLPI in GLPI 10.0.6
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item instanceof Profile) {
            self::showForProfile($item);
        }
        return true;
    }

    public static function showForProfile(Profile $profile): void {
        global $DB;

        $profiles_id = (int) $profile->getID();
        $can_edit    = Session::haveRight('profile', UPDATE);

        $rights = [
            'plugin_swat_form'  => 'SWAT Forms (create, view, edit)',
            'plugin_swat_admin' => 'SWAT Administration (logs, all users forms)',
        ];

        $bits = [
            READ   => 'Read',
            CREATE => 'Create',
            UPDATE => 'Update',
            DELETE => 'Delete',
            PURGE  => 'Purge',
        ];

        echo '<div class="card mt-3">';
        echo '<div class="card-header" style="background:#006B6B;color:#C8E000;font-weight:700;">';
        echo '<i class="fas fa-hard-hat me-2"></i>SWAT – Start Work Assessment Tool';
        echo '</div>';
        echo '<div class="card-body p-0">';
        echo '<table class="table table-hover table-sm mb-0">';
        echo '<thead class="table-dark"><tr><th>Right</th>';
        foreach ($bits as $bit => $label) {
            echo "<th class='text-center'>{$label}</th>";
        }
        echo '</tr></thead><tbody>';

        foreach ($rights as $field => $label) {
            $current = 0;
            $res = $DB->query("SELECT `rights` FROM `glpi_profilerights` WHERE `profiles_id` = {$profiles_id} AND `name` = '{$field}'");
            if ($res && $row = $DB->fetchAssoc($res)) {
                $current = (int) $row['rights'];
            }
            echo "<tr><td><strong>{$label}</strong></td>";
            foreach ($bits as $bit => $rlabel) {
                $checked  = ($current & $bit) ? 'checked' : '';
                $disabled = $can_edit ? '' : 'disabled';
                echo "<td class='text-center'>
                    <input type='checkbox'
                           class='form-check-input swat-right-cb'
                           data-field='{$field}'
                           data-bit='{$bit}'
                           data-profiles-id='{$profiles_id}'
                           {$checked} {$disabled}>
                </td>";
            }
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';

        if ($can_edit) {
            $ajax_url = Plugin::getWebDir('swat', false) . '/ajax/saverights.php';
            $token    = Session::getNewCSRFToken();
            echo "<script>
            document.querySelectorAll('.swat-right-cb').forEach(function(cb){
                cb.addEventListener('change', function(){
                    var field = this.dataset.field;
                    var pid   = this.dataset.profilesId;
                    var total = 0;
                    document.querySelectorAll('.swat-right-cb[data-field=\"'+field+'\"]').forEach(function(c){
                        if(c.checked) total |= parseInt(c.dataset.bit);
                    });
                    fetch('{$ajax_url}',{
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:'_glpi_csrf_token='+encodeURIComponent('{$token}')+'&profiles_id='+pid+'&field='+encodeURIComponent(field)+'&rights='+total
                    });
                });
            });
            </script>";
        }
    }

    public static function initProfile(): void {
        global $DB;
        if (!isset($_SESSION['glpiactiveprofile']['id'])) return;
        $pid = (int) $_SESSION['glpiactiveprofile']['id'];
        foreach (['plugin_swat_form', 'plugin_swat_admin'] as $rname) {
            $res = $DB->query("SELECT `rights` FROM `glpi_profilerights` WHERE `profiles_id`={$pid} AND `name`='{$rname}'");
            $_SESSION['glpiactiveprofile'][$rname] = ($res && $row = $DB->fetchAssoc($res)) ? (int)$row['rights'] : 0;
        }
    }
}
