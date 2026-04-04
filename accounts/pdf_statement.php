<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAuth();

// ATTEMPT TO LOAD FPDF
// You must have fpdf.php in your includes or libs folder.
// If it's missing, this script will show instructions.
if(!file_exists('../includes/fpdf.php')) {
    header('Content-Type: text/html');
    echo "<div style='font-family: sans-serif; padding: 2rem; border: 2px dashed #ccc; text-align: center;'>";
    echo "<h2><i class='ph ph-warning'></i> FPDF Library Not Found</h2>";
    echo "<p>To generate server-side PDFs, you must download <b>fpdf.php</b> and place it in the <b>includes/</b> folder.</p>";
    echo "<a href='http://www.fpdf.org/en/download.php' target='_blank' style='background: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Download FPDF</a>";
    echo "</div>";
    exit();
}

require('../includes/fpdf.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT a.*, m.first_name, m.last_name, m.member_no, m.address, 
        s.scheme_name, s.interest_rate 
        FROM accounts a 
        JOIN members m ON a.member_id = m.id 
        JOIN schemes s ON a.scheme_id = s.id 
        WHERE a.id = $id";
        
$res = mysqli_query($conn, $sql);
$acc = mysqli_fetch_assoc($res);

if(!$acc) die("Account not found.");

$txns = mysqli_query($conn, "SELECT * FROM transactions WHERE account_id = $id ORDER BY transaction_date ASC");

class PDF extends FPDF {
    function Header() {
        global $conn;
        $bank_name = getSetting($conn, 'bank_name');
        $bank_address = getSetting($conn, 'bank_address');
        $this->SetFont('Arial','B',16);
        $this->Cell(0,10,strtoupper($bank_name),0,1,'C');
        $this->SetFont('Arial','',8);
        $this->Cell(0,5,$bank_address,0,1,'C');
        $this->Ln(5);
        $this->SetFont('Arial','B',12);
        $this->SetFillColor(240,240,240);
        $this->Cell(0,10,'OFFICIAL ACCOUNT STATEMENT',1,1,'C', true);
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
        $this->Cell(0,10,'Generated on '.date('d-m-Y H:i').' (Computer Generated Statement)',0,0,'R');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Account Info
$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,7,'Customer:',0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(60,7,strtoupper($acc['first_name'].' '.$acc['last_name']),0,0);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,7,'A/C Number:',0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,7,$acc['account_no'],0,1);

$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,7,'Member ID:',0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(60,7,$acc['member_no'],0,0);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,7,'A/C Type:',0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,7,$acc['account_type'].' ('.$acc['scheme_name'].')',0,1);

$pdf->Ln(10);

// Table Header
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(230,230,230);
$pdf->Cell(35,8,'Date',1,0,'C',true);
$pdf->Cell(70,8,'Particulars',1,0,'L',true);
$pdf->Cell(28,8,'Debit (Dr)',1,0,'R',true);
$pdf->Cell(28,8,'Credit (Cr)',1,0,'R',true);
$pdf->Cell(29,8,'Balance',1,1,'R',true);

$pdf->SetFont('Arial','',8);
while($t = mysqli_fetch_assoc($txns)) {
    $date = date('d-m-Y', strtotime($t['transaction_date']));
    $desc = $t['description'];
    
    // Logic for Dr/Cr
    $is_credit = ($acc['account_type'] == 'Loan') 
                ? in_array($t['transaction_type'], ['EMI', 'Deposit']) 
                : in_array($t['transaction_type'], ['Deposit', 'Interest', 'Account-Open', 'EMI']);
                
    $dr = !$is_credit ? number_format(abs($t['amount']), 2) : '-';
    $cr = $is_credit ? number_format(abs($t['amount']), 2) : '-';
    $bal = number_format(abs($t['balance_after']), 2);
    $bal_type = $t['balance_after'] < 0 ? 'Dr' : 'Cr';

    $pdf->Cell(35,7,$date,1,0,'C');
    $pdf->Cell(70,7,substr($desc, 0, 40),1,0,'L');
    $pdf->Cell(28,7,$dr,1,0,'R');
    $pdf->Cell(28,7,$cr,1,0,'R');
    $pdf->Cell(29,7,$bal.' '.$bal_type,1,1,'R');
}

$pdf->Ln(10);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,7,'*** End of Statement ***',0,1,'C');

$pdf->Output('I', 'Statement-'.$acc['account_no'].'.pdf');
?>
