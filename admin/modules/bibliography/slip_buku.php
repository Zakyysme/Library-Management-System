<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

 /* This source modified by Muh Tarom (stuqly@gmail.com) on Friday, 21 December 2012 ( SLiMS 5 Cendana )/
 /* Disesuaikan Pada tanggal 28 September , untuk SLiMS 7 Cendana oleh Zaemakhrus /

/* Cetak Kartu Buku */
/* Tutorial Baca Di slimskudus.blogspot.com */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');
// start the session
require SB.'admin/default/session.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');

if (!$can_read) {
	die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

$max_print = 50;

/* RECORD OPERATION */
if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
	if (!$can_read) {
		die();
	}
	if (!is_array($_POST['itemID'])) {
		// make an array
		$_POST['itemID'] = array((integer)$_POST['itemID']);
	}
	// loop array
	if (isset($_SESSION['bookcards'])) {
		$print_count = count($_SESSION['bookcards']);
	} else {
		$print_count = 0;
	}
	// barcode size
	$size = 2;
	// create AJAX request
	echo '<script type="text/javascript" src="'.JWB.'jquery.js"></script>';
	echo '<script type="text/javascript">';
	// loop array
	foreach ($_POST['itemID'] as $itemID) {
		if ($print_count == $max_print) {
			$limit_reach = true;
			break;
		}
		if (isset($_SESSION['bookcards'][$itemID])) {
			continue;
		}
		if (!empty($itemID)) {
			$barcode_text = trim($itemID);
			/* replace space */
			$barcode_text = str_replace(array(' ', '/', '\/'), '_', $barcode_text);
			/* replace invalid characters */
			$barcode_text = str_replace(array(':', ',', '*', '@'), '', $barcode_text);
			// send ajax request
			echo 'jQuery.ajax({ url: \''.SWB.'lib/phpbarcode/barcode.php?code='.$itemID.'&encoding='.$sysconf['barcode_encoding'].'&scale='.$size.'&mode=png\', type: \'GET\', error: function() { alert(\'Error creating barcode!\'); } });'."\n";
			// add to sessions
			$_SESSION['bookcards'][$itemID] = $itemID;
			$print_count++;
		}
	}
	echo 'top.$(\'#queueCount\').html(\''.$print_count.'\')';
	echo '</script>';
	// update print queue count object
	sleep(2);
	if (isset($limit_reach)) {
		$msg = str_replace('{max_print}', $max_print, __('Selected items NOT ADDED to print queue. Only {max_print} can be printed at once'));
		utility::jsAlert($msg);
	} else {
	  utility::jsAlert(__('Selected items added to print queue'));
	}
	exit();
}

// clean print queue
if (isset($_GET['action']) AND $_GET['action'] == 'clear') {
    utility::jsAlert(__('Print queue cleared!'));
    echo '<script type="text/javascript">top.$(\'#queueCount\').html(\'0\');</script>';
    unset($_SESSION['labels']);
    exit();
}

// barcode pdf download
if (isset($_GET['action']) AND $_GET['action'] == 'print') {
	// check if label session array is available
	if (!isset($_SESSION['bookcards'])) {
		utility::jsAlert(__('There is no data to print!'));
		die();
	}
	if (count($_SESSION['bookcards']) < 1) {
		utility::jsAlert(__('There is no data to print!'));
		die();
	}

	// concat all ID together
	$item_ids = '';
	foreach ($_SESSION['bookcards'] as $id) {
		$item_ids .= '\''.$id.'\',';
	}
	// strip the last comma
	$item_ids = substr_replace($item_ids, '', -1);

	// send query to database
	$item_q = $dbs->query('SELECT i.item_id, i.biblio_id, i.item_code, i.inventory_code, i.call_number, b.biblio_id, b.title, b.sor FROM item AS i
	LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
	WHERE i.item_code IN('.$item_ids.')');

	$item_data_array = array();
	while ($item_d = $item_q->fetch_row()) {
		if ($item_d[0]) {
			$item_data_array[] = $item_d;
		}
	}
	
	// include printed settings configuration file
	require SB.'admin'.DS.'admin_template'.DS.'printed_settings.inc.php';
	// check for custom template settings
	$custom_settings = SB.'admin'.DS.$sysconf['admin_template']['dir'].DS.$sysconf['template']['theme'].DS.'printed_settings.inc.php';
	if (file_exists($custom_settings)) {
		include $custom_settings;
	}
	// chunk book card array
	$chunked_book_card_arrays = array_chunk($item_data_array, $bookslip_items_per_row);
	// create html ouput
	$html_str .= '<!DOCTYPE html>'."\n";
	$html_str .= '<!-- Contributed(@)2012 Muh Tarom (stuqly@gmail.com) -->'."\n";
	$html_str .= '<html lang="en">'."\n";
	$html_str .= '<head>'."\n";
	$html_str .= '<title>Hasil Cetak Slip Buku</title>'."\n";
	$html_str .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'."\n";
	$html_str .= '<meta http-equiv="Pragma" content="no-cache" />'."\n";
	$html_str .= '<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, post-check=0, pre-check=0" />'."\n";
	$html_str .= '<meta http-equiv="Expires" content="Sat, 26 Jul 1997 05:00:00 GMT" />'."\n";
	$html_str .= '<script type="text/javascript" src="../js/jquery.js"></script>'."\n";
	$html_str .= '<style type="text/css">'."\n";
	$html_str .= 'body { background: #ffffff; color: #000000; font-family: '.$bookslip_fonts.'; font-size: 9pt; margin: 1cm; padding: 0px; }'."\n";
	$html_str .= '.bookcard { min-height: '.$bookslip_height.'; width: '.$bookslip_width.'; margin: 0.5cm; }'."\n";
	$html_str .= '.libname { border-bottom: #000000 2px solid; font-size: 10pt; font-weight: bold; margin: 0px; padding: 0px 0px 5px 0px; text-align: center; width: 100%; }'."\n";
	$html_str .= '</style>'."\n";
	$html_str .= '</head>'."\n";
	$html_str .= '<body>'."\n";
    $html_str .= '<a href="#" onclick="window.print()">Print Again</a>'."\n";
	$html_str .= '<table style="margin: 0; padding: 0;" cellspacing="0" cellpadding="0">'."\n";
	// loop the chunked arrays to row
	foreach ($chunked_book_card_arrays as $bookslip_rows) {
		$html_str .= '<tr>'."\n";
		foreach ($bookslip_rows as $bookslip) {
			$html_str .= '<td valign="top">'."\n";
				$html_str .= '<div class="bookcard">'."\n";
				if ($bookslip_include_header_text) { $html_str .= '<div class="libname">'.($bookslip_header_text?$bookslip_header_text:$sysconf['library_name']).'<br />'.($bookslip_address_text).'</div>'."\n";}
				$html_str .= '<table style="margin: 5px 0px 0px 0px; padding: 0; width: 8.6cm;" cellspacing="0" cellpadding="0">'."\n";
				$html_str .= '<tr>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; width: 80px;">No. Inv</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; width: 6px;">:</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0;">'.$bookslip[3].'</td>'."\n";
				$html_str .= '</tr>'."\n";
				$html_str .= '<tr>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; width: 80px;">Judul</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; width: 6px;">:</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0;">';
				if ($bookslip_cut_title) {
					$html_str .= substr($bookslip[6], 0, $bookslip_cut_title).'..';
				} else { $html_str .= $bookslip[6]; }
				$html_str .= '</td>'."\n";
				$html_str .= '</tr>'."\n";
				$html_str .= '<tr>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; width: 80px;">Pengarang</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; width: 6px;">:</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0;">';
				$author_q = $dbs->query('SELECT b.biblio_id, b.title, a.author_name FROM biblio AS b
					LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
					LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
					WHERE b.biblio_id='.$bookslip[1]);
				$authors = '';
				while ($author_d = $author_q->fetch_row()) {
					$bookslip[6] = $author_d[1];
					$authors .= $author_d[2].' - ';
				}
				$authors = substr_replace($authors, '', -2);
				if ($bookslip_cut_authors) {
					$html_str .= substr($authors, 0, $bookslip_cut_authors).'..';
				} else { $html_str .= $authors; }
				$html_str .= '</td>'."\n";
				$html_str .= '</tr>'."\n";
				$html_str .= '<tr>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; width: 80px;">Call Number</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; width: 6px;">:</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0;">'.$bookslip[4].'</td>'."\n";
				$html_str .= '</tr>'."\n";
				$html_str .= '</table>'."\n";
				$rows = $bookslip_number_row;
				$html_str .= '<table style="margin: 5px 0px 0px 0px; padding: 0; width: 8.6cm;" cellspacing="0" cellpadding="0" border="1px">'."\n";
				$html_str .= '<tr>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; text-align: center; width: 0.7cm;">No.</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; text-align: center; width: 3.3cm;">Nama</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; text-align: center; width: 3.5cm;">Tgl. Kembali</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; text-align: center; width: 1.1cm;">Paraf</td>'."\n";
				$html_str .= '</tr>'."\n";
				for($tr=1;$tr<=$rows;$tr++){
				$html_str .= '<tr>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; text-align: center; width: 0.7cm;">&nbsp;</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; text-align: center; width: 3.5cm;">&nbsp;</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; text-align: center; width: 3.3cm;">&nbsp;</td>'."\n";
				$html_str .= '<td valign="top" style="margin: 0; text-align: center; width: 1.1cm;">&nbsp;</td>'."\n";
				$html_str .= '</tr>'."\n";}
				$html_str .= '</table>'."\n";
				$html_str .= '</div>'."\n";
				$html_str .= '</td>'."\n";
		}
	$html_str .= '</tr>'."\n";
	}
	$html_str .= '</table>'."\n";
    $html_str .= '<script type="text/javascript">self.print();</script>'."\n";
	$html_str .= '</body>'."\n";
	$html_str .= '</html>'."\n";
	// unset the session
	unset($_SESSION['bookcards']);
	// write to file
	$print_file_name = 'bookcard_gen_print_result_'.strtolower(str_replace(' ', '_', $_SESSION['uname'])).'.html';
	$file_write = @file_put_contents(UPLOAD.$print_file_name, $html_str);
	if ($file_write) {
		// update print queue count object
		echo '<script type="text/javascript">parent.$(\'#queueCount\').html(\'0\');</script>';
		// open result in window
		echo '<script type="text/javascript">top.$.colorbox({href: "'.SWB.FLS.'/'.$print_file_name.'", iframe: true, width: 800, height: 500, title: "Hasil Cetak Slip Buku"})</script>';
	} else { utility::jsAlert('ERROR! Item bookcards failed to generate, possibly because '.SB.FLS.' directory is not writable'); }
	exit();
}

/* search form */
?>
<fieldset class="menuBox">
<div class="menuBoxInner printIcon">
	<div class="per_title">
    <h2><?php echo __('Cetak Slip Buku'); ?></h2>
  </div>
	<div class="sub_section">
    <div class="btn-group">
      <a target="blindSubmit" href="<?php echo MWB; ?>bibliography/slip_buku.php?action=clear" class="notAJAX btn btn-default"><i class="glyphicon glyphicon-trash"></i>&nbsp;<?php echo __('Clear Print Queue'); ?></a>
      <a target="blindSubmit" href="<?php echo MWB; ?>bibliography/slip_buku.php?action=print" class="notAJAX btn btn-default"><i class="glyphicon glyphicon-print"></i>&nbsp;<?php echo __('Cetak Slip Buku Dari Data Terpilih'); ?></a>
	</div>
    <form name="search" action="<?php echo MWB; ?>bibliography/slip_buku.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
    <input type="text" name="keywords" size="30" />
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="btn btn-default" />
    </form>
    </div>
	<div class="infoBox">
	<?php
	echo __('Maximum').' <font style="color: #f00">'.$max_print.'</font> '.__('records can be printed at once. Currently there is').' ';
	if (isset($_SESSION['bookcards'])) {
		echo '<font id="queueCount" style="color: #f00">'.count($_SESSION['bookcards']).'</font>';
	} else { echo '<font id="queueCount" style="color: #f00">0</font>'; }
	echo ' '.__('in queue waiting to be printed.');
	?>
	</div>
</div>
</fieldset>
<?php
/* search form end */

// create datagrid
$datagrid = new simbio_datagrid();
/* ITEM LIST */
require SIMBIO.'simbio_UTILS/simbio_tokenizecql.inc.php';
require LIB.'biblio_list_model.inc.php';
// index choice
if ($sysconf['index']['type'] == 'index' || ($sysconf['index']['type'] == 'sphinx' && file_exists(LIB.'sphinx/sphinxapi.php'))) {
	if ($sysconf['index']['type'] == 'sphinx') {
		require LIB.'sphinx/sphinxapi.php';
		require LIB.'biblio_list_sphinx.inc.php';
	} else {
		require LIB.'biblio_list_index.inc.php';
	}
	// table spec
	$table_spec = 'item LEFT JOIN search_biblio AS `index` ON item.biblio_id=`index`.biblio_id';
	$datagrid->setSQLColumn('item.item_code',
		'item.inventory_code AS \''.__('Item Code').'\'',
		'item.call_number AS \''.__('Call Number').'\'',
		'index.title AS \''.__('Title').'\'');
} else {
	require LIB.'biblio_list.inc.php';
	// table spec
	$table_spec = 'item LEFT JOIN biblio ON item.biblio_id=biblio.biblio_id';
	$datagrid->setSQLColumn('item.item_code',
		'item.inventory_code AS \''.__('Inventory Code').'\'',
		'item.call_number AS \''.__('Call Number').'\'',
		'biblio.title AS \''.__('Title').'\'');
}
$datagrid->setSQLorder('item.last_update DESC');
// is there any search
if (isset($_GET['keywords']) AND $_GET['keywords']) {
	$keywords = $dbs->escape_string(trim($_GET['keywords']));
	$searchable_fields = array('title', 'author', 'subject', 'class', 'callnumber', 'itemcode');
	$search_str = '';
	// if no qualifier in fields
	if (!preg_match('@[a-z]+\s*=\s*@i', $keywords)) {
		foreach ($searchable_fields as $search_field) {
			$search_str .= $search_field.'='.$keywords.' OR ';
		}
	} else {
		$search_str = $keywords;
	}
	$biblio_list = new biblio_list($dbs, 20);
	$criteria = $biblio_list->setSQLcriteria($search_str);
}
if (isset($criteria)) {
	$datagrid->setSQLcriteria('('.$criteria['sql_criteria'].')');
}
// set table and table header attributes
$datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
// edit and checkbox property
$datagrid->edit_property = false;
$datagrid->chbox_property = array('itemID', __('Add'));
$datagrid->chbox_action_button = __('Add To Print Queue');
$datagrid->chbox_confirm_msg = __('Add to print queue?');
$datagrid->column_width = array('15%', '15%', '70%');
// set checkbox action URL
$datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
// put the result into variables
$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, $can_read);
if (isset($_GET['keywords']) AND $_GET['keywords']) {
	$msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords'));
	echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>';
}
echo $datagrid_result;
/* main content end */
