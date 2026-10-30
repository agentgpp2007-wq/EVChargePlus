<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$currentUser = user();
$userId = 0;

if ($currentUser) {
    $userId = (int) (
        $currentUser['User_id']
        ?? $currentUser['user_id']
        ?? $currentUser['id']
        ?? 0
    );

    if ($userId <= 0 && !empty($currentUser['email'])) {
        $stmt = db()->prepare('SELECT User_id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$currentUser['email']]);
        $userId = (int) $stmt->fetchColumn();
    }
} elseif (($_GET['action'] ?? '') !== 'ask') {
    redirect('login.php');
}

/**
 * Generate a database-backed answer for the EVChargePlus AI Assistant.
 */
function evcp_answer(string $question, int $userId): string
{
    $question = trim($question);

    if ($question === '') {
        return 'Please ask a question so I can help you with your EV charging needs.';
    }

    if (mb_strlen($question) > 500) {
        return 'Please keep your question under 500 characters.';
    }

    $q = mb_strtolower($question);
    $pdo = db();

    try {
        // 1. CANCELLATION QUESTIONS
        if (preg_match('/\b(cancel|cancellation|cancelling|how to cancel)\b/', $q)) {
            if ($userId <= 0) {
                return "To cancel a booking, log in to your account, go to Dashboard -> My Charging Bookings, and click 'Cancel Booking'.";
            }

            $stmt = $pdo->prepare("
                SELECT b.booking_id, b.booking_date, b.start_time, cs.station_name, c.charger_number
                FROM bookings b
                JOIN chargers c ON c.charger_id = b.charger_id
                JOIN chargingstations cs ON cs.station_id = c.station_id
                WHERE b.user_id = ?
                  AND b.booking_status = 'BOOKED'
                  AND (b.booking_date > CURDATE() OR (b.booking_date = CURDATE() AND b.start_time > CURTIME()))
                ORDER BY b.booking_date ASC
            ");
            $stmt->execute([$userId]);
            $active = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($active) {
                $list = [];
                foreach ($active as $bk) {
                    $list[] = "• Booking #{$bk['booking_id']} at {$bk['station_name']} (Charger #{$bk['charger_number']}) on {$bk['booking_date']} at " . substr((string)$bk['start_time'], 0, 5);
                }
                return "You currently have active booking(s) eligible for cancellation:\n"
                    . implode("\n", $list) . "\n\n"
                    . "👉 How to cancel: Go to your Dashboard, scroll to 'My Charging Bookings', find the booking, and click the 'Cancel Booking' button.";
            }

            return "You have no upcoming bookings to cancel right now.\n\n"
                . "💡 Good to know: You can cancel any future booking free of charge at any time prior to the slot start time directly from your Dashboard.";
        }

        // 2. RESCHEDULING QUESTIONS
        if (preg_match('/\b(reschedule|rescheduling|change time|change slot|change date|move booking)\b/', $q)) {
            if ($userId <= 0) {
                return "To reschedule a charging slot, log in to your account, open your Dashboard, and click 'Reschedule' on any active booking.";
            }

            $stmt = $pdo->prepare("
                SELECT b.booking_id, b.booking_date, b.start_time, cs.station_name
                FROM bookings b
                JOIN chargers c ON c.charger_id = b.charger_id
                JOIN chargingstations cs ON cs.station_id = c.station_id
                WHERE b.user_id = ?
                  AND b.booking_status = 'BOOKED'
                  AND (b.booking_date > CURDATE() OR (b.booking_date = CURDATE() AND b.start_time > CURTIME()))
                ORDER BY b.booking_date ASC
            ");
            $stmt->execute([$userId]);
            $active = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($active) {
                $list = [];
                foreach ($active as $bk) {
                    $list[] = "• Booking #{$bk['booking_id']} at {$bk['station_name']} on {$bk['booking_date']} at " . substr((string)$bk['start_time'], 0, 5);
                }
                return "You have active booking(s) that can be rescheduled:\n"
                    . implode("\n", $list) . "\n\n"
                    . "👉 How to reschedule: Go to your Dashboard, locate your booking, click 'Reschedule Booking', pick your new date and available time slot, and confirm!";
            }

            return "You don't have any active upcoming bookings to reschedule.\n\n"
                . "💡 How rescheduling works: If you have an upcoming booking, you can choose a new date and available time slot from your Dashboard without having to make a new payment.";
        }

        // 3. BOOKINGS QUESTIONS
        if (preg_match('/\b(booking|bookings|reservation|reservations|slot|slots|my slot)\b/', $q)) {
            if ($userId <= 0) {
                return "Please log in to your account to view your bookings and reservations.";
            }

            $stmt = $pdo->prepare("
                SELECT
                    b.booking_id,
                    b.booking_date,
                    b.start_time,
                    b.end_time,
                    b.booking_status,
                    c.charger_number,
                    cs.station_name,
                    cs.city
                FROM bookings b
                JOIN chargers c ON c.charger_id = b.charger_id
                JOIN chargingstations cs ON cs.station_id = c.station_id
                WHERE b.user_id = ?
                ORDER BY b.booking_date DESC, b.start_time DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                return "You don't have any bookings yet.\n\nYou can reserve a charger anytime using our Book a Slot page (booking.php) or browse Stations (stations.php).";
            }

            $results = [];
            foreach ($rows as $row) {
                $statusEmoji = ($row['booking_status'] === 'COMPLETED') ? '✓' : (($row['booking_status'] === 'BOOKED') ? '⚡' : '✗');
                $results[] = "{$statusEmoji} Booking #{$row['booking_id']}: {$row['station_name']} ({$row['city']})\n"
                    . "   Charger #{$row['charger_number']} | Date: {$row['booking_date']} (" . substr((string)$row['start_time'], 0, 5) . " - " . substr((string)$row['end_time'], 0, 5) . ")\n"
                    . "   Status: {$row['booking_status']}";
            }

            return "Here are your latest bookings:\n\n"
                . implode("\n\n", $results)
                . "\n\nManage all your reservations on your Dashboard.";
        }

        // 4. REWARDS AND POINTS
        if (preg_match('/\b(reward|rewards|point|points|points balance|redeem|bonus|earn|loyalty)\b/', $q)) {
            if ($userId <= 0) {
                return "EVChargePlus members earn 10 reward points for every completed charging session! Log in to check your reward points balance.";
            }

            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(points_earned), 0) AS total_earned,
                    COALESCE(SUM(points_redeemed), 0) AS total_redeemed
                FROM rewards
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $rRow = $stmt->fetch(PDO::FETCH_ASSOC);

            $earned = (int)($rRow['total_earned'] ?? 0);
            $redeemed = (int)($rRow['total_redeemed'] ?? 0);
            $balance = max(0, $earned - $redeemed);

            return "⭐ Your EVChargePlus Rewards Summary:\n"
                . "• Available Points Balance: {$balance} points\n"
                . "• Total Points Earned: {$earned} points\n"
                . "• Points Redeemed: {$redeemed} points\n\n"
                . "💡 How rewards work: You earn 10 reward points automatically whenever a charging session is marked completed. Points can be redeemed for discounts on future charges!";
        }

        // 5. VEHICLE QUESTIONS
        if (preg_match('/\b(vehicle|vehicles|car|cars|ev|evs|battery|connector)\b/', $q)) {
            if ($userId <= 0) {
                return "Please log in to view the electric vehicles registered to your EVChargePlus profile.";
            }

            $stmt = $pdo->prepare("
                SELECT brand, model, vehicle_number, battery_capacity, connector_type
                FROM vehicles
                WHERE user_id = ?
                ORDER BY vehicle_id DESC
            ");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                return "You haven't registered an electric vehicle yet.\n\nOpen My Vehicles (vehicles.php) to add your EV details (brand, model, license plate, battery capacity, connector type).";
            }

            $results = [];
            foreach ($rows as $row) {
                $results[] = "🚗 " . trim($row['brand'] . ' ' . $row['model']) . "\n"
                    . "   Plate: {$row['vehicle_number']} | Battery: {$row['battery_capacity']} kWh | Connector: {$row['connector_type']}";
            }

            return "Your registered vehicles:\n\n"
                . implode("\n\n", $results)
                . "\n\nYou can add or edit vehicles on the My Vehicles page.";
        }

        // 6. PRICE AND RATE QUESTIONS
        if (preg_match('/\b(price|prices|rate|rates|cost|tariff|kwh|cheap|cheapest)\b/', $q)) {
            $stmt = $pdo->query("
                SELECT
                    cs.station_name,
                    cs.city,
                    c.charger_number,
                    c.charger_type,
                    c.power_rating,
                    c.price_per_kwh
                FROM chargers c
                JOIN chargingstations cs ON cs.station_id = c.station_id
                WHERE c.charger_status = 'AVAILABLE'
                  AND cs.station_status = 'ACTIVE'
                ORDER BY c.price_per_kwh ASC
                LIMIT 6
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                return "Charger pricing is updated by our administrators. Standard rates range between ₹8.00 and ₹22.00 per kWh depending on charger type (AC / DC Fast / Ultra-Fast).";
            }

            $results = [];
            foreach ($rows as $row) {
                $results[] = "• {$row['station_name']} ({$row['city']}): Charger #{$row['charger_number']} ({$row['charger_type']}, {$row['power_rating']} kW) — ₹" . number_format((float)$row['price_per_kwh'], 2) . "/kWh";
            }

            return "⚡ Live charger rates (ordered from lowest to highest):\n\n"
                . implode("\n", $results)
                . "\n\n💡 Tip: AC chargers are typically more economical (₹8–₹12/kWh), while DC Fast & Ultra-Fast chargers provide rapid charging at ₹14–₹22/kWh.";
        }

        // 7. STATION AND LOCATION QUESTIONS
        if (preg_match('/\b(station|stations|charger|chargers|location|locations|where|find|pune|mumbai|hinjawadi|wakad|aundh|pimpri)\b/', $q)) {
            // Check if specific city was asked
            $cityFilter = '';
            $params = [];
            if (preg_match('/(mumbai|pune|hinjawadi|wakad|aundh|pimpri)/i', $q, $mCity)) {
                $cityFilter = " AND (city LIKE ? OR location LIKE ? OR station_name LIKE ?) ";
                $paramVal = '%' . $mCity[1] . '%';
                $params = [$paramVal, $paramVal, $paramVal];
            }

            $stmt = $pdo->prepare("
                SELECT station_name, location, city, opening_time, closing_time, total_chargers
                FROM chargingstations
                WHERE station_status = 'ACTIVE' {$cityFilter}
                ORDER BY station_name
                LIMIT 6
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                return "No active stations matched that location. Browse our full list of stations on the Stations page (stations.php).";
            }

            $results = [];
            foreach ($rows as $row) {
                $hours = substr((string)$row['opening_time'], 0, 5) . " - " . substr((string)$row['closing_time'], 0, 5);
                $results[] = "📍 {$row['station_name']} ({$row['city']})\n"
                    . "   Address: {$row['location']}\n"
                    . "   Hours: {$hours} | Total Chargers: {$row['total_chargers']}";
            }

            return "Active EVChargePlus Stations:\n\n"
                . implode("\n\n", $results)
                . "\n\nSelect any station on the Stations page to check real-time charger availability and book slots.";
        }

        // 8. BILLING, QR CODE, AND RECEIPT
        if (preg_match('/\b(receipt|receipts|bill|billing|invoice|qr|qr code|proof)\b/', $q)) {
            return "🧾 About EVChargePlus Bills & QR Receipts:\n"
                . "• When any charging session is marked completed, our system automatically generates a detailed Tax Invoice & Bill.\n"
                . "• The receipt features a scannable QR Code that anyone can scan with their phone camera to view and verify session details online.\n"
                . "• You can view, print, or download your receipts at any time from your Dashboard or the Charging History page!";
        }

        // 9. CALCULATOR & ESTIMATION
        if (preg_match('/\b(calculate|calculator|estimate|range|duration|how long|time to charge)\b/', $q)) {
            return "🧮 Smart Charging Calculator:\n"
                . "You can use our Smart Calculator (track_charging.php) to estimate:\n"
                . "• Required energy in kWh (based on your vehicle's battery capacity and current vs target %)\n"
                . "• Charging duration (hours and minutes)\n"
                . "• Extra driving range added (km)\n"
                . "• Total estimated charging cost (₹)\n"
                . "• Live cost comparison across all stations to help you find the best rate!";
        }

        // 10. CHARGING HISTORY & ANALYTICS
        if (preg_match('/\b(history|spending|spent|energy|analytics|expense|expenses|cost per km)\b/', $q)) {
            if ($userId <= 0) {
                return "Please log in to review your personal charging history and usage analytics.";
            }

            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS count_hist,
                    COALESCE(SUM(energy_kwh), 0) AS total_energy,
                    COALESCE(SUM(estimated_cost), 0) AS total_spend,
                    COALESCE(SUM(range_added_km), 0) AS total_range
                FROM charging_history
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $h = $stmt->fetch(PDO::FETCH_ASSOC);

            $energy = (float)($h['total_energy'] ?? 0);
            $spend = (float)($h['total_spend'] ?? 0);
            $range = (float)($h['total_range'] ?? 0);
            $costPerKm = ($range > 0) ? ($spend / $range) : 0;

            return "📊 Your Usage Analytics Snapshot:\n"
                . "• Saved Calculations: {$h['count_hist']}\n"
                . "• Total Energy: " . number_format($energy, 1) . " kWh\n"
                . "• Total Estimated Spend: ₹" . number_format($spend, 2) . "\n"
                . "• Estimated Range Added: " . number_format($range, 1) . " km\n"
                . "• Average Cost per km: ₹" . number_format($costPerKm, 2) . "/km\n\n"
                . "View complete monthly charts and vehicle breakdowns on the Charging History page (charging_history.php).";
        }

        // 11. GREETINGS
        if (preg_match('/\b(hello|hi|hey|good morning|good evening|good afternoon|help)\b/', $q)) {
            $nameStr = $currentUser['name'] ?? 'there';
            return "Hello {$nameStr}! 👋 Welcome to EVChargePlus AI Assistant.\n\n"
                . "I can help you with:\n"
                . "• 📅 Checking your bookings & slots\n"
                . "• ❌ How to cancel or reschedule bookings\n"
                . "• 🚗 Looking up your registered vehicles\n"
                . "• ⭐ Checking your reward points balance\n"
                . "• 📍 Finding charging stations & live rates\n"
                . "• 🧾 Viewing bills & QR code receipts\n\n"
                . "What would you like to know?";
        }

        // 12. FALLBACK
        return "I can answer questions using real EVChargePlus database information.\n\n"
            . "Try asking:\n"
            . "• Show my bookings\n"
            . "• How do I cancel or reschedule my booking?\n"
            . "• What vehicles do I have registered?\n"
            . "• How many reward points do I have?\n"
            . "• Which charging station is cheapest?\n"
            . "• Show charging stations in Pune / Mumbai\n"
            . "• How does the QR code receipt work?";

    } catch (Throwable $e) {
        error_log('EVChargePlus Assistant Error: ' . $e->getMessage());
        return "I encountered a problem retrieving information. Please try again in a moment.";
    }
}

// AJAX CHAT ENDPOINT
if (
    ($_GET['action'] ?? '') === 'ask'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    header('Content-Type: application/json; charset=utf-8');

    $raw = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($raw['csrf_token'] ?? $raw['csrf'] ?? ''));
    $t1 = (string) ($_SESSION['csrf_token'] ?? '');
    $t2 = (string) ($_SESSION['csrf'] ?? '');

    $tokenMatches = false;
    if ($token !== '') {
        if ($t1 !== '' && hash_equals($t1, $token)) {
            $tokenMatches = true;
        } elseif ($t2 !== '' && hash_equals($t2, $token)) {
            $tokenMatches = true;
        }
    }

    // Never lock out an authenticated logged-in session user on conversational inquiries
    if (!$tokenMatches && $userId <= 0 && $token !== '') {
        http_response_code(419);
        echo json_encode(['answer' => 'Security token mismatch. Please refresh and try again.']);
        exit;
    }

    $question = trim((string) ($raw['question'] ?? ''));

    if ($question === '' || mb_strlen($question) > 500) {
        http_response_code(400);
        echo json_encode(['answer' => 'Please enter a question of up to 500 characters.']);
        exit;
    }

    echo json_encode(
        ['answer' => evcp_answer($question, $userId)],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

$page_title = 'EVChargePlus AI Assistant';
$csrf = csrf_token();

require __DIR__ . '/partials/header.php';
?>

<style>
.ai-page {
    max-width: 950px;
    margin: 115px auto 60px;
    padding: 0 20px;
    color: #18352a;
}

.ai-heading {
    text-align: center;
    margin-bottom: 28px;
}

.ai-heading .ai-symbol {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 68px;
    height: 68px;
    border-radius: 22px;
    background: #dcf8e9;
    font-size: 34px;
    margin-bottom: 12px;
}

.ai-heading h1 {
    font-size: clamp(26px, 4vw, 36px);
    margin: 0 0 10px;
    color: #123c2b;
}

.ai-heading p {
    color: #6a8074;
    margin: 0;
    line-height: 1.6;
}

.ai-panel {
    border: 1px solid #dcebe2;
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 18px 55px rgba(18, 65, 43, .08);
    overflow: hidden;
}

.ai-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 25px;
    color: white;
    background: linear-gradient(135deg, #003b2f 0%, #087f50 100%);
}

.ai-panel-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ai-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.ai-messages {
    height: 460px;
    overflow-y: auto;
    padding: 24px;
    background: #fbfdfc;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.ai-bubble {
    max-width: 80%;
    padding: 14px 18px;
    border-radius: 16px;
    font-size: 14px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}

.ai-bubble.assistant {
    align-self: flex-start;
    background: white;
    border: 1px solid #e1ede6;
    color: #17362b;
    border-bottom-left-radius: 4px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
}

.ai-bubble.user {
    align-self: flex-end;
    background: #087f50;
    color: white;
    border-bottom-right-radius: 4px;
}

.ai-chips {
    padding: 12px 24px;
    background: #f3faf6;
    border-top: 1px solid #e1ede6;
    display: flex;
    gap: 8px;
    overflow-x: auto;
    white-space: nowrap;
}

.ai-chip {
    padding: 7px 12px;
    background: white;
    border: 1px solid #c8dfd3;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #087f50;
    cursor: pointer;
    transition: all 0.2s;
}

.ai-chip:hover {
    background: #087f50;
    color: white;
    border-color: #087f50;
}

.ai-input-bar {
    padding: 18px 24px;
    background: white;
    border-top: 1px solid #e1ede6;
    display: flex;
    gap: 12px;
}

.ai-input-bar input {
    flex: 1;
    min-height: 48px;
    padding: 10px 16px;
    border: 1.5px solid #d7e6de;
    border-radius: 12px;
    font: inherit;
    font-size: 14px;
    color: #17362b;
    outline: none;
}

.ai-input-bar input:focus {
    border-color: #087f50;
    box-shadow: 0 0 0 3px rgba(8, 127, 80, 0.15);
}

.ai-input-bar button {
    padding: 0 24px;
    background: #087f50;
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s;
}

.ai-input-bar button:hover {
    background: #066640;
}

@media (max-width: 600px) {
    .ai-page {
        margin-top: 95px;
        padding: 0 12px;
    }
    .ai-bubble {
        max-width: 90%;
    }
}
</style>

<div class="ai-page">
    <div class="ai-heading">
        <div class="ai-symbol">⚡</div>
        <h1>EVChargePlus AI Assistant</h1>
        <p>Ask questions about your live bookings, vehicle specs, station rates, reward points, and cancellations.</p>
    </div>

    <div class="ai-panel">
        <div class="ai-panel-header">
            <div class="ai-panel-header-left">
                <div class="ai-avatar">🤖</div>
                <div>
                    <strong style="font-size:16px;">EV Assistant</strong>
                    <div style="font-size:12px;opacity:0.85;">Connected to Live Database · Online</div>
                </div>
            </div>
            <span style="font-size:12px;background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:20px;">⚡ System Smart Agent</span>
        </div>

        <div class="ai-messages" id="chatMessages">
            <div class="ai-bubble assistant">Hello <?= e($currentUser['name'] ?? '') ?>! 👋 I am your EVChargePlus AI Assistant.

I can provide instant, verified answers from your database:
• Check your active bookings & slots
• Assist with cancellations and rescheduling
• Show your registered EV details
• Check your reward points balance
• Find station locations, charger types & rates

What would you like to ask?</div>
        </div>

        <div class="ai-chips">
            <div class="ai-chip" data-q="Show my latest bookings">📅 My Bookings</div>
            <div class="ai-chip" data-q="How do I cancel or reschedule my booking?">❌ Cancel / Reschedule</div>
            <div class="ai-chip" data-q="What vehicles do I have registered?">🚗 My Vehicles</div>
            <div class="ai-chip" data-q="How many reward points do I have?">⭐ My Rewards</div>
            <div class="ai-chip" data-q="Which charging station has the cheapest rates?">💰 Cheapest Station</div>
            <div class="ai-chip" data-q="Show active stations and locations">📍 Stations</div>
            <div class="ai-chip" data-q="How does the QR code bill work?">🧾 QR Code Bill</div>
        </div>

        <form class="ai-input-bar" id="chatForm">
            <input type="text" id="chatInput" placeholder="Ask about bookings, stations, vehicles, rewards..." maxlength="500" required autocomplete="off">
            <button type="submit" id="sendBtn">Send</button>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const chatForm = document.getElementById("chatForm");
    const chatInput = document.getElementById("chatInput");
    const chatMessages = document.getElementById("chatMessages");
    const sendBtn = document.getElementById("sendBtn");
    const csrfToken = <?= json_encode($csrf) ?>;

    function appendMessage(text, role) {
        const div = document.createElement("div");
        div.className = "ai-bubble " + role;
        div.textContent = text;
        chatMessages.appendChild(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    async function sendQuestion(question) {
        if (!question) return;
        appendMessage(question, "user");
        chatInput.value = "";
        chatInput.disabled = true;
        sendBtn.disabled = true;

        const loadingDiv = document.createElement("div");
        loadingDiv.className = "ai-bubble assistant";
        loadingDiv.textContent = "Checking system records...";
        chatMessages.appendChild(loadingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        try {
            const resp = await fetch("ai_agent.php?action=ask", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({ question: question })
            });
            const data = await resp.json();
            loadingDiv.textContent = data.answer || "Sorry, I could not retrieve an answer right now.";
        } catch (err) {
            loadingDiv.textContent = "Network error. Please try asking again.";
        } finally {
            chatInput.disabled = false;
            sendBtn.disabled = false;
            chatInput.focus();
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    chatForm.addEventListener("submit", function (e) {
        e.preventDefault();
        const q = chatInput.value.trim();
        if (q) sendQuestion(q);
    });

    document.querySelectorAll(".ai-chip").forEach(chip => {
        chip.addEventListener("click", function () {
            sendQuestion(this.dataset.q);
        });
    });
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
