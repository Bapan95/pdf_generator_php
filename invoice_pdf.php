<?php
require_once("../lib/config.php");
require_once("../lib/constants.php");
require_once('../Classes/PHPExcel.php');

// require_once('../lib/dompdf/autoload.inc.php'); // Include mdpf
// // reference the Dompdf namespace
// use Dompdf\Dompdf;

$logged_user_id = my_session('user_id');
if (isset($_REQUEST['source']) && ($_REQUEST['source'] == 'app')) {
	$logged_user_id = $_REQUEST['user_id'];
}
$action_type = $_REQUEST['action_type'];
$return_data  = array();

if ($action_type == "DOWNLOAD_PDF") {

	require_once('../lib/mpdf60/mpdf.php'); // Include mdpf
	//$mpdf = new mPDF('utf-8', array(190, 236));
	$mpdf = new mPDF('utf-8', 'A4-P');
	//$mpdf = new mPDF('utf-8', array(254,190));
	//$mpdf = new mPDF('utf-8', 'A5-P');
	$mpdf->setAutoTopMargin = 'stretch'; // Set pdf top margin to stretch to avoid content overlapping
	$mpdf->setAutoBottomMargin = 'stretch'; // Set pdf bottom margin to stretch to avoid content overlapping
	//$mpdf->autoPageBreak = false;

	$html = '';
	$invoice_id = intval($_REQUEST['invoice_id']);

	//	GET Stamp and Signature
	$stamp = "";
	$sign = "";
	if ($_REQUEST['with_sign_stamp'] == 1) {
		$query_1 = "SELECT IFNULL(upload_seal, '') upload_seal FROM company_master WHERE company_id = 2;";
		$result_1 = $db->query($query_1);
		$data_1 = mysqli_fetch_assoc($result_1);
		if ($data_1['upload_seal'] != '') {
			$stamp = $data_1['upload_seal'];
		}

		$query_2 = "SELECT IFNULL(upload_signature, '') upload_signature FROM user_master WHERE user_id = '" . $logged_user_id . "';";
		$result_2 = $db->query($query_2);
		$data_2 = mysqli_fetch_assoc($result_2);
		if ($data_2['upload_signature'] != '') {
			$sign = $data_2['upload_signature'];
		}
	}
	//echo 'stamp: ' . $stamp . ' | sign: ' . $sign; exit();
	// -----------------------



	$query_header = "SELECT ih.invoice_id, ih.invoice_no, ih.po_no, DATE_FORMAT(IFNULL(ih.email_date, '0000-00-00'), '%d/%m/%Y') email_date, ih.booking_id, DATE_FORMAT(ih.generated_date, '%d/%m/%Y') invoice_date, DATE_FORMAT(ih.invoice_from, '%d/%m/%Y') invoice_from, DATE_FORMAT(ih.invoice_to, '%d/%m/%Y') invoice_to, ih.hsn_sac_no, ih.gross_amount, ih.net_amount, ih.cgst_percent, ih.cgst_amount, ih.sgst_percent, ih.sgst_amount, ih.igst_percent, ih.igst_amount, ih.payable_amount, ih.remarks, ih.status, cm.pan,  cm.gstin, cm.company_name, cm.company_shortname, cm.address, cm.village_town, cm.pin_code, bh.booking_no, bh.brand_name, u.name, c.client_name, ifnull(c.address,'') AS client_address, IFNULL(st.state_code, 19) cust_state_code, c.pan cust_pan, c.gstin cust_gstin, ih.discount_percent, ih.discount_amount,ih.inv_type, bam.account_no, bam.ifsc_no, bam.branch_name, bm.bank_name, bam.branch_address 
	FROM ooh_invoice_header ih 
	LEFT JOIN booking_header bh ON ih.booking_id = bh.booking_id 
	LEFT JOIN user_master u ON u.user_id = bh.created_by 
	LEFT JOIN client_master c ON c.client_id = bh.client_id
	LEFT JOIN state_master st ON st.state_id = c.state_id
	LEFT JOIN company_master cm ON cm.company_id = bh.company_id
	LEFT JOIN bank_account_master bam ON bam.bank_account_id = ih.bank_account_id
	LEFT JOIN bank_master bm ON bm.bank_id = bam.bank_id
	WHERE ih.invoice_id = '" . $invoice_id . "'";

	$result_header = $db->query($query_header);
	$data_header = mysqli_fetch_assoc($result_header);

	$query_detail = "SELECT id.site_id, s.site_code, s.site_name, lt.light_type_name, l.location_name, m.media_vh_name, s.width, s.height, s.sqft, s.face_side, st.site_type_name, id.rate, cal_actual_rent_func(id.rate, ih.invoice_from, ih.invoice_to, '', '') amount, IFNULL(id.quantity,1) quantity, id.invoice_from, id.invoice_to, id.final_rate ,id.site_psudo_name
	FROM ooh_invoice_detail id 
	INNER JOIN ooh_invoice_header ih ON ih.invoice_id = id.invoice_id 
	INNER JOIN site_master s ON s.site_id = id.site_id 
	INNER JOIN location_master l ON l.location_id = s.location_id 
	INNER JOIN media_vehicle m ON m.media_vh_id = s.media_vh_id 
	INNER JOIN site_type_master st ON st.site_type_id = s.site_type_id 
	INNER JOIN light_type_master lt ON lt.light_type_id = s.light_type_id 
	WHERE ih.invoice_id = '" . $invoice_id . "' 
	ORDER BY id.site_id;";

	$html .= '<html>
    <head>
        <style>
            /** 
                Set the margins of the page to 0, so the footer and the header
                can be of the full height and width !
             **/
            @page {
                size: 21cm 29.7cm;
				margin: 0mm 10mm;
				footer: pagefooter;
            }
			
            /** Define now the real margins of every page in the PDF **/
            body {
                margin-top: 1mm;
                margin-left: 10mm;
                margin-right: 10mm;
                margin-bottom: 1mm;
            }
			
            /** Define the footer rules **/
            footer {
                position: fixed; 
                bottom: 0mm; 
                left: 10mm; 
                right: 10mm;
                height: 10mm;
            }
			.pagenum:before { content: counter(page, decimal); }
        </style>
    </head>
    <body>
	
	<pagefooter name="pagefooter" content-right="{PAGENO}/{nbpg}" footer-style="font-size: 7pt;" />
	<div style="text-align:center;">
	<table width="90%" border="0" cellspacing="0" cellpadding="0" align="center">
	  <tr><td height="10"></td></tr>
	  <tr>
		<td style="border:1px solid #000; border-bottom:none;" align="left" valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="0">
		  <tr>
			<td width="58%" align="left" valign="top" style="padding-left:15px;font-size:10px; font-family:Arial, sans-serif;"><table width="100%" border="0" cellspacing="0" cellpadding="0">
			  <tr>
				<td width="74%" align="left" valign="top" style="padding:10px">
				  <p style="margin:0; padding:0;"><img src="../assets/images/logo.jpg" width="225" height="86" alt=""/></p>
				  <p style="margin:0;">&nbsp;</p>
				  <p style="margin:0;"><b>Regd. Office :</b> </p>
				  <p style="margin:0;">174/1, Raja Ram Mohan Roy Road, </p>
				  <p style="margin:0;">Kolkata - 700 008</p>
				  <p style="margin:0;">&nbsp;</p>
				  <p style="margin:0;"><b>Correspondence Address :</b> </p>
				  <p style="margin:0;">783, D.H. Road, Kolkata - 700008. </p>
				  <p style="margin:0;"><b>Ph :</b> (033) 2447 3472 / (+91) 933 102 1671</p>
				  <p style="margin:0;">&nbsp;</p>
				  <p style="margin:0;"><b>PAN No. :</b>  AAXCS1759E / <b>GSTIN :</b> 19AAXCS1759E1ZQ</p>
				  <p style="margin:0;"><b>CIN NO. :</b> U74999WB2016 PTC 215681</p>
				  <p style="margin:0;"><b>PLACE OF SUPPLY :</b> WEST BENGAL / <b>STATE CODE :</b> 19</p>
				  <p style="margin:0;"><b>E-mail :</b> signature.advertising15@gmail.com</p>
				  <p style="margin:0;"><b>MSME No. :</b> WB18F0027702</p>
				</td>';
	if ($data_header['inv_type'] == 'T') {
		$html .= '<td width="26%" align="left" valign="top" style="padding-top:10px;font-size: 17px; font-family:Arial, sans-serif;"><b><u>Tax Invoice</u></b></td>';
	}
	if ($data_header['inv_type'] == 'E') {
		$html .= '<td width="26%" align="left" valign="top" style="padding-top:10px;font-size: 17px; font-family:Arial, sans-serif;"><b><u>Estimate</u></b></td>';
	}

	$html .= '</tr>
					<tr>
				<td colspan="2" align="left" valign="top"></td>
				</tr>
			</table></td>
			<td width="42%" align="left" valign="top" style="padding-left:15px;font-size:10px; font-family:Arial, sans-serif;">
			  <table>
				<tr>
				<td width="30%" height="108" align="left" valign="top" style="margin:0;">&nbsp;</td>
				</tr>
				<tr>
				<td width="30%" align="left" valign="top" style="margin:0;">
				  <p style="margin:0;"><b>Invoice No :</b> <span id="invoice_no">' . $data_header['invoice_no'] . '</span></p>
				  <p style="margin-top:0;"><b>Date :</b> <span id="invoice_date">' . $data_header['invoice_date'] . '</span> </p>
				  <p style="margin:0;font-size:8px;">&nbsp;</p>
				  <p style="margin:0;"><b>Order No :</b> <span id="po_no"></span>' . $data_header['po_no'] . '</p>
				  <p style="margin-top:0;"><b>Email dated :</b> <span id="email_date">' . $data_header['email_date'] . '</span></p>
				  <p style="margin:0;">&nbsp;</p>
				  <p style="margin-top:0;"><b>Campaign Name :</b> <span id="campaign_name">' . $data_header['brand_name'] . '</span></p>
				  <p style="margin:0;">&nbsp;</p>
				</td>
				</tr>
				<tr>
				<td width="30%" align="left" valign="top" style="margin:0;">
				  <p style="margin:0;"><b>Buyer&rsquo;s Name : <span id="cust_name" style="color:#0585CF;">' . $data_header['client_name'] . '</span></b></p>
				  <p style="margin:0;"><b>Address : </b><span id="cust_address">' . $data_header['client_address'] . '</span></p>
				  <p style="margin:0;"><b>GSTIN : </b><span id="cust_gstin">' . $data_header['cust_gstin'] . '</span></p>
				  <p style="margin:0;"><b>Customer&rsquo;s PAN No. : </b><span id="cust_pan">' . $data_header['cust_pan'] . '</span> / <b>State Code : </b><span id="cust_state">' . $data_header['cust_state_code'] . '</span></p>
				  <p style="margin:0;"></p>
				</td>
				</tr>
			  </table>		  
			  </td>
		  </tr>
		</table></td>
	  </tr>
	  <tr>
		<td align="left" valign="top" style="font-size:10px; font-family:Arial, sans-serif;">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
		  <!--
		  <tr>
			<td colspan="9">
				<table width="100%" border="0" cellpadding="0" cellspacing="0">
					<tr>
						<td width="35%"><img src="../assets/images/logo.jpg" width="225" height="86" alt=""/></td>
						<td width="30%" align="center">';
	if ($data_header['inv_type'] == 'T') {

		$html .= '<strong><u>Tax Invoice</u></strong>';
	}
	if ($data_header['inv_type'] == 'E') {

		$html .= '<strong><u>Estimate</u></strong>';
	}
	$html .= '</td>
						<td width="35%">&nbsp;</td>
					</tr>
				</table>
			</td>
		  </tr>
		  <tr>
			<td colspan="3"><p style="margin:0;"><b>Regd. Office :</b> </p></td>
			<td colspan="6"><p style="margin:0;"><b>Invoice No :</b> <span id="invoice_no">' . $data_header['invoice_no'] . '</span></p></td>
		  </tr>
		  <tr>
			<td colspan="3"><p style="margin:0;">3/33, Brojomoni Debya Road  Flat-D, 3rd Floor, </p></td>
			<td colspan="6"><p style="margin-top:0;"><b>Date :</b> <span id="invoice_date">' . $data_header['invoice_date'] . '</span> </p></td>
		  </tr>
		  -->
		  
		  <thead>
		  <tr>
			<td rowspan="2" width="4%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">SL No.</td>
			<td rowspan="2" width="60%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Description</td>
			<td rowspan="2" width="6%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">SAC/HSN</td>
			<td rowspan="2" width="6%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Size</td>
			<td colspan="2" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Period</td>
			<td rowspan="2" width="3%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Qty</td> 
			<td rowspan="2" width="7%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Space <br />Charges Rate</td>
			<td rowspan="2" width="7%" align="center" valign="middle" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Amount</td>
		  </tr>
		  <tr>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;">From</td>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;">To</td>
		  </tr>
		  </thead>
		  <tr>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;"></td>
			<td align="left" valign="middle" style="font-size:13px; font-family:Arial, sans-serif;border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;"><b>To, Being the charges of space for Advertisement on </b><br />Billboards / Hoarding / Gantry / Double-Decker / Metro Pillar / Pole-kiosk / Signal / Signage / Mobile Van at
			</td>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;"><b id="hsn_sac_no">' . $data_header['hsn_sac_no'] . '</b></td>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;"></td>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;"></td>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;" id="invoice_from"></td>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;" id="invoice_to"></td>
			<td align="right" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;padding-right:10px;"></td>
			<td align="right" valign="middle" style="border-left:1px solid #000;border-right:1px solid #000; border-bottom:1px solid #000;padding-right:10px;"></td>
		  </tr>
		  <tbody>';
	$i = 0;
	$p = 1;
	$maxRow = 32;
	$rate = 0;
	$result_detail = $db->query($query_detail);
	$no_of_item = $result_detail->num_rows;
	while ($data_detail = mysqli_fetch_assoc($result_detail)) {
		$i++;
		if (((($i - 1) % $maxRow) == 0) && ($i > 1)) {
			//$mpdf->autoPageBreak = true;

			//$html .= $mpdf->AddPage();
			$html .= "</tbody></table></td></tr></table>";
			$html .= "<pagebreak />";
			$p++;
			$html .= '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td align="left" valign="top" style="font-size:10px; font-family:Arial, sans-serif;"><table width="100%" border="0" cellpadding="0" cellspacing="0">
				<thead>
		  <tr>
			<td rowspan="2" width="4%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">SL No.</td>
			<td rowspan="2" width="60%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Description</td>
			<td rowspan="2" width="6%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">SAC/HSN</td>
			<td rowspan="2" width="6%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Size</td>
			<td colspan="2" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Period</td>
			<td rowspan="2" width="3%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Qty</td> 
			<td rowspan="2" width="7%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Space <br />Charges Rate</td>
			<td rowspan="2" width="7%" align="center" valign="middle" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000; border-top:1px solid #000;">Amount</td>
		  </tr>
		  <tr>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;">From</td>
			<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;">To</td>
		  </tr>
		  </thead>
				<tbody>';
			//$html .= "<br /> ". $i;
		}
		$rate = $data_detail['rate'] / $data_detail['quantity'];
		$html .= '<tr>';
		$html .= '<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;">' . $i . '</td>';
		$html .= '<td align="left" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;">' . $data_detail['site_psudo_name'] . '</td>';
		$html .= '<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;"></td>';
		$html .= '<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;">' . $data_detail['width'] . '&apos; X ' . $data_detail['height'] . '&apos;</td>';
		$html .= '<td width="9%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;">' . $data_detail['invoice_from'] . '</td>';
		$html .= '<td width="8%" align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;">' . $data_detail['invoice_to'] . '</td>';
		$html .= '<td align="center" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;">' . $data_detail['quantity'] . '</td>';
		$html .= '<td align="right" valign="middle" style="border-left:1px solid #000; border-bottom:1px solid #000;padding-left:10px;padding-right:10px;">' . sprintf('%0.2f', $rate) . '</td>';
		$html .= '<td align="right" valign="middle" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;padding-left:10px; padding-right:10px;">' . $data_detail['final_rate'] . '</td>';
		$html .= '</tr>';



		// if(($i % $maxRow) > 0){
		// 		//$html .= '<tr><td></td></tr>';
		// 		//$html .= $mpdf->autoPageBreak = true;
		// 	}




	}

	// if (($no_of_item % $maxRow) > 0) {
	// 	//$height = 30 * ($maxRow - ($i % $maxRow));
	// 	//$html .= '<tr><td colspan="9" height="' .$height. '" align="right" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;">&nbsp;</td></tr>';
	// 	if ($p > 1) {
	// 		for ($j = ($no_of_item % $maxRow); $j < $maxRow; $j++) {
	// 			$html .= '<tr>
	// 							<td colspan="9" style="border-left:1px solid #000; border-right:1px solid #000; padding:5px;text-align:right;">&nbsp;</td>
	// 						</tr>';
	// 		}
	// 	} else {
	// 		for ($j = ($no_of_item % $maxRow); $j < 24; $j++) {
	// 			$html .= '<tr>
	// 							<td colspan="9" style="border-left:1px solid #000; border-right:1px solid #000; padding:5px;text-align:right;">&nbsp;</td>
	// 						</tr>';
	// 		}
	// 	}
	// }
	$html .= '<!-- <tr height="' . $height . '">
			<td colspan="9" style="border-left:1px solid #000; border-right:1px solid #000; padding:10px;text-align:right;">&nbsp;</td>
		  </tr> -->
		  <tr>
			<td colspan="9" style="border-left:1px solid #000; border-bottom:1px solid #000; border-right:1px solid #000; padding:10px;text-align:right;">&nbsp;</td>
		  </tr>
		  </tbody>
		  <tr>
			<td colspan="8" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;text-align:right;">Gross Amount</td>
			<td align="right" id="gross_amt" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;padding-right:10px;">' . $data_header['gross_amount'] . '</td>
		  </tr>';
	if ($data_header['discount_percent'] > 0) {
		$html .= '<tr>
			<td colspan="8" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;text-align:right;">Discount %</td>
			<td align="right" id="dis_per" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;padding-right:10px;">' . $data_header['discount_percent'] . '</td>
		  </tr>';
	} else {
		$html .= '<tr>
			<td colspan="8" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;text-align:right;">Discount Amount</td>
			<td align="right" id="dis_amt" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;padding-right:10px;">' . $data_header['discount_amount'] . '</td>
		  </tr>';
	}
	$html .= '<tr>
			<td colspan="8" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;text-align:right;">Net Amount</td>
			<td align="right" id="amount" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;padding-right:10px;">' . $data_header['net_amount'] . '</td>
		  </tr>';
	if ($data_header['igst_percent'] > 0) {
		$html .= '<tr id="igst_row">
			<td colspan="8" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;text-align:right;">Add: IGST @<span id="igst_percent">' . $data_header['igst_percent'] . '</span>%</td>
			<td align="right" id="igst_amount" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;padding-right:10px;">' . $data_header['igst_amount'] . '</td>
		  </tr>';
	} else {
		$html .= '<tr id="cgst_row">
			<td colspan="8" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;text-align:right;">Add: CGST @<span id="cgst_percent">' . $data_header['cgst_percent'] . '</span>% </td>
			<td align="right" id="cgst_amount" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;padding-right:10px;">' . $data_header['cgst_amount'] . '</td>
		  </tr>
		  <tr id="sgst_row">
			<td colspan="8" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;text-align:right;">Add: SGST @<span id="sgst_percent">' . $data_header['sgst_percent'] . '</span>%</td>
			<td align="right" id="sgst_amount" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;padding-right:10px;">' . $data_header['sgst_amount'] . '</td>
		  </tr>';
	}

	$word = convertToIndianCurrency($data_header['payable_amount']);
	//echo $word; die;

	$html .= '<tr>
			<td colspan="8" style="border-left:1px solid #000; border-bottom:1px solid #000;padding:10px;text-align:right;">In Word : ' . $word . ' || Total  :<span id="word"><span></td>
			<td align="right" id="final_amount" style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000;padding-right:10px;">' . $data_header['payable_amount'] . '</td>
		  </tr>
		</table></td>
	  </tr>
	  <tr>
		<td align="left" valign="top" style="font-size:14px; font-family:Arial, sans-serif;"><table width="100%" border="0" cellspacing="0" cellpadding="0">
		  <tr>
			<td width="60%" style="padding-right:35px; border-left:1px solid #000; padding-left:10px; border-bottom:1px solid #000;">
			<p style="margin-top:0;margin-bottom:5px; text-align:left;padding-left: 100px">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 
				<u>Bank Account Details</u>
			</p>
			<p style="margin:0;"><i>In favour of</i>: <b style="color:#0585CF;">SIGNATURES ADVERTISING PRIVATE LIMITED</b></p>
			<p style="margin:0;">Account No. : <span id="accno">' . $data_header['account_no'] . '</span> | IFSC Code No: <span id="ifsc">' . $data_header['ifsc_no'] . '</span></p>
			  <p style="margin:0;">Benificiary Bank Name : <span id="bank_nm">' . $data_header['bank_name'] . '</span> Branch : <span id="branch_nm">' . $data_header['branch_name'] . '</span></p>  
			</td>
			<td style="border-bottom:1px solid #000;" align="right" valign="top">
			<table width="100%" border="0" cellspacing="0" cellpadding="0" align="right">
	  <tr>';


	//	for Stamp and Signature
	if ($_REQUEST['with_sign_stamp'] == 1) {
		if ($stamp != '') {
			$html .= '<td align="right" width="160" height="160" valign="middle"> <div><img src="../upload_file/' . $stamp . '"></div> </td>';
		} else {
			$html .= '<td align="right" width="160" height="160" valign="middle"> <div></div> </td>';
		}
		if ($sign != '') {
			$html .= '<td align="center" width="160" height="160" valign="middle" style="background-image:url(../upload_file/' . $sign . '); background-image-resize:6;"><p style="padding:15px">For</p></td>';
		} else {
			$html .= '<td align="right" width="160" height="160" valign="middle" style=""><p style="padding:15px">For</p></td>';
		}
	} else {
		$html .= '<td align="right" width="160" height="160" valign="middle"> <div></div> </td>
		<td align="right" width="160" height="160" valign="middle" style=""><p style="padding:15px">For</p></td>';
	}
	// -----------------------

	$html .= '<td align="center" valign="top" style="border-right:1px solid #000; padding-top:10px; padding-bottom:10px;">
			<p style="margin:0;">E. &. O. E</p>
			<p style="margin:0;">&nbsp;</p>
			<p><img src="../assets/images/logo.jpg" width="102" height="39" alt=""/></p> 
			<p style="margin:0;">&nbsp;</p>
			<p style="margin:0;">Authorised Signatory</p>
		</td>
	  </tr>
	
	</table>


	</td>
		  </tr>
		  <tr>
			<td colspan="2" style="font-size:10px; padding-right:35px; border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000; padding-left:10px; padding-top:5px;">
			<p style="margin-top:0;margin-bottom:5px; text-align:left;">
				<b style="text-decoration:underline;">Terms & Conditions</b>: 
				<span>(i) Bills are to be paid via Cheque / NEFT / RTGS / DD. </span>
				<span>(ii) The above bill has been raised in accordance with your Purchase Order/Contract. </span>
				<span>(iii) This bill/invoice is strictly payable within 40 days from the 1st date of display, if not interest will be charged @ 24% p.a. </span>
				<span>(iv) Interest will be charged @ 18% on applicable GST, if it is not paid within 30 days. So, if the bill/invoice amount will not be paid within the exact period then the outstanding cannot be negotiated in future on term of payment over GST. </span>
				<span>(v) Any complaints will not be entertained in respect of this bill/invoice, after 10 days of presentation. </span>
				<span>(vi) All disputes are subject to Kolkata Jurisdiction. </span>
			</p>
			</td>
			<!-- <td style="padding-right:35px; border-right:1px solid #000; border-bottom:1px solid #000;padding-left:10px;">&nbsp;</td> -->
		  </tr>
		  
		</table></td>
	  </tr>
	  <tr><td height="20"></td></tr>
	</table>
	</div>
	</body>
	</html>';

	//echo json_encode($html); exit;


	$mpdf->WriteHTML($html);

	$file_name =  APP_NAME . '_INVOICE_' . time();
	$pdf_file = $file_name . '.pdf';
	$target_path = "/var/www/html/pdf/";
	$target_paths = $target_path . basename($pdf_file);
	// echo $target_paths;die;
	$mpdf->Output($target_paths, 'F');
	$return_data =  array(
		'status' => true, 'file_name' => $pdf_file, 'html' => $html
	);
	echo json_encode($return_data);
	// exit;

	// $path       = 'tmp/';
	// $file_name =  APP_NAME . '_INVOICE_' . time();
	// $pdf_file = $file_name . '.pdf';
	// $stylesheet = '<style>' . file_get_contents('assets/css/bootstrap.min.css') . '</style>';  // Read the css file
	// $mpdf->WriteHTML($stylesheet, 1);  //             
	// // $mpdf->WriteHTML($html, 2);
	// $mpdf->Output($path . $pdf_file, "F");
}
