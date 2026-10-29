<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../dbconn.php';

$today = date('Y-m-d');

$sql = "SELECT pme.hodid, pme.staffname, pme.staffno, pme.training_title, 
               DATE_FORMAT(pme.from_date, '%e/%c/%Y') AS formatted_from_date, 
               DATE_FORMAT(pme.to_date, '%e/%c/%Y') AS formatted_to_date,
               DATE_FORMAT(training.startdate, '%e/%c/%Y') AS formatted_startdate,
               training.venue
        FROM pme 
        JOIN training ON pme.trainingid = training.id
        WHERE DATE_ADD(pme.to_date, INTERVAL 1 DAY) = ?
          AND pme.designation IN ('Executive', 'MANAGER (AM/HOS & ABOVE)') 
          AND pme.status = 'pending'
          AND NOT EXISTS (SELECT 1 FROM participation pa WHERE pa.id = pme.participationid AND pa.attendance = 'ABSENT')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$hod_subordinates = [];
while ($row = $result->fetch_assoc()) {
    $hodid = $row['hodid'];
    if (!isset($hod_subordinates[$hodid])) {
        $hod_subordinates[$hodid] = [];
    }
    $hod_subordinates[$hodid][] = $row;
}

$hod_emails = [];
$hod_ids = array_keys($hod_subordinates);
if (!empty($hod_ids)) {
    $placeholders = implode(',', array_fill(0, count($hod_ids), '?'));
    $email_sql = "SELECT id, email FROM user WHERE id IN ($placeholders)";
    $email_stmt = $conn->prepare($email_sql);
    $email_stmt->bind_param(str_repeat('i', count($hod_ids)), ...$hod_ids);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    while ($email_row = $email_result->fetch_assoc()) {
        $hod_emails[$email_row['id']] = $email_row['email'];
    }
}

foreach ($hod_subordinates as $hodid => $subordinates) {
    if (!isset($hod_emails[$hodid])) {
        echo "No email found for HOD ID: $hodid, skipping...<br>";
        continue;
    }
    
    $hod_email = $hod_emails[$hodid];
    $message = "<p style='font-size: 1.17em; font-style: italic;'>Assalamualaikum Warahmatullahi Wabarakatuh & Salam Sejahtera,</p>";
    $message .= "<h4 style='font-style: italic;'>Greetings From Learning & Development, PHN Industry Sdn Bhd.</h4>";
    $message .= "<p>Dear All,<br><br>As part of our continuous effort to ensure the effectiveness of training programmes, we are conducting a Performance Monitoring Evaluation for the following training session:</p>";
    $message .= "<table border='1' cellpadding='5' cellspacing='0'>
                    <tr>
                        <th>Name</th>
                        <th>Staff No</th>
                        <th>Training Title</th>
                        <th>Venue</th>
                        <th>Date</th>
                    </tr>";
    foreach ($subordinates as $sub) {
        $message .= "<tr>
                        <td>{$sub['staffname']}</td>
                        <td>{$sub['staffno']}</td>
                        <td>{$sub['training_title']}</td>
                        <td>{$sub['venue']}</td>
                        <td>{$sub['formatted_startdate']}</td>
                    </tr>";
    }
    $message .= "</table>";

    $message .= "<h4> Evaluation Period:</h4>";
    $message .= "<p>The evaluation period started on <strong>{$sub['formatted_from_date']}</strong> and will conclude on <strong>{$sub['formatted_to_date']}</strong>. During this time, participants are expected to apply their newly acquired knowledge and skills in their roles.</p>";

    $message .= "<h4> Action Required:</h4>";
    $message .= "<ul>";
    $message .= "<li><strong>For Participants:</strong> Please <strong>Meet with your HOD</strong> to go through your <strong>Performance Monitoring Evaluation Form</strong> and discuss how you have applied the training in your tasks.</li>";
    $message .= "<li><strong>For HODs:</strong> Assess your staff's performance, provide feedback on their progress, and suggest areas for improvement.</li>";
    $message .= "</ul>";

    $message .= "<p>The completed <strong>Performance Monitoring Evaluation Form</strong> must be <strong>reviewed and agreed upon by both the participant and the HOD</strong> before submission.</p>";
    $message .= "<h4> Submission Portal:</h4>";
    $message .= "<p>Please submit the completed evaluation form via the following link: <a href='https://portal.phn.com.my/ldms' target='_blank' style='color: #FFA73B;'>https://portal.phn.com.my/ldms</a></p>";
    $message .= "Kindly return the completed evaluation form to the <strong>Learning & Development (L&D)</strong> by the dateline.";

    $message .= "<p>Should you require any further clarification, please do not hesitate to contact us. We appreciate your cooperation in ensuring the effectiveness of our training programs.</p>";

    $message .= "<p><strong>Thank You.</strong></p>";
    $message .= "<p>--This is an auto-generated email, no reply is needed--</p>";
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.office365.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'server.info@phn.com.my'; 
        $mail->Password   = 'P@ssw0rd';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

         $mail->setFrom('server.info@phn.com.my', 'PHN System');
         $mail->addAddress($hod_email);
         $mail->isHTML(true);
         $mail->Subject = 'Perfomance Monitoring Evaluation Notification - Pending Evaluation';
         $mail->Body    = $message;

        $mail->send();
        echo "Email sent to HOD ID: $hodid ($hod_email) <br>";
    } catch (Exception $e) {
        echo "Email failed for HOD ID: $hodid ($hod_email). Error: {$mail->ErrorInfo} <br>";
    }
}

$conn->close();
?>
