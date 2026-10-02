import os
from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import nsdecls, qn

def set_cell_background(cell, hex_color):
    """Sets background color of a table cell."""
    tcPr = cell._element.get_or_add_tcPr()
    shd = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{hex_color}"/>')
    tcPr.append(shd)

def set_cell_margins(cell, top=140, bottom=140, left=200, right=200):
    """Sets cell padding."""
    tcPr = cell._element.get_or_add_tcPr()
    tcMar = parse_xml(f'''
        <w:tcMar {nsdecls("w")}>
            <w:top w:w="{top}" w:type="dxa"/>
            <w:bottom w:w="{bottom}" w:type="dxa"/>
            <w:left w:w="{left}" w:type="dxa"/>
            <w:right w:w="{right}" w:type="dxa"/>
        </w:tcMar>
    ''')
    tcPr.append(tcMar)

def create_manual():
    doc = Document()

    # Set page margins
    for section in doc.sections:
        section.top_margin = Inches(0.8)
        section.bottom_margin = Inches(0.8)
        section.left_margin = Inches(0.8)
        section.right_margin = Inches(0.8)

    # Base styles
    normal_style = doc.styles['Normal']
    normal_style.font.name = 'Calibri'
    normal_style.font.size = Pt(11)
    normal_style.font.color.rgb = RGBColor(30, 41, 59) # Slate 800

    # ==========================================
    # TITLE & HEADER
    # ==========================================
    title_p = doc.add_paragraph()
    title_p.paragraph_format.space_before = Pt(10)
    title_p.paragraph_format.space_after = Pt(4)
    run_title = title_p.add_run("GEO AUCTION & LIVE BIDDING PLATFORM")
    run_title.font.name = 'Arial'
    run_title.font.size = Pt(24)
    run_title.font.bold = True
    run_title.font.color.rgb = RGBColor(4, 120, 87) # Emerald Primary

    sub_p = doc.add_paragraph()
    sub_p.paragraph_format.space_after = Pt(20)
    run_sub = sub_p.add_run("Senior Full-Stack Architecture, Deep Code Audit & Technical Operational Manual")
    run_sub.font.name = 'Calibri'
    run_sub.font.size = Pt(13)
    run_sub.font.italic = True
    run_sub.font.color.rgb = RGBColor(100, 116, 139) # Slate 500

    # Meta Info Box
    meta_table = doc.add_table(rows=2, cols=2)
    meta_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    meta_table.autofit = False

    meta_data = [
        [("Platform Version:", True), ("Geo Auction v2.4 (Enterprise PHP/MySQL)", False)],
        [("Lead Architect:", True), ("Senior Full-Stack PHP & Web Systems Expert", False)]
    ]
    for r_idx, row in enumerate(meta_table.rows):
        for c_idx, cell in enumerate(row.cells):
            set_cell_background(cell, "F1F5F9")
            set_cell_margins(cell, top=100, bottom=100, left=150, right=150)
            p = cell.paragraphs[0]
            label, is_bold = meta_data[r_idx][c_idx]
            run = p.add_run(label)
            run.font.bold = is_bold
            run.font.size = Pt(10)
            if is_bold:
                run.font.color.rgb = RGBColor(15, 23, 42)

    doc.add_paragraph().paragraph_format.space_after = Pt(15)

    # Helper function for headings
    def add_section_heading(text, level=1):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(18)
        p.paragraph_format.space_after = Pt(8)
        run = p.add_run(text)
        run.font.name = 'Arial'
        run.font.bold = True
        if level == 1:
            run.font.size = Pt(16)
            run.font.color.rgb = RGBColor(4, 120, 87) # Emerald
        else:
            run.font.size = Pt(13)
            run.font.color.rgb = RGBColor(15, 23, 42)
        return p

    def add_red_file_box(file_name, desc_text):
        """Creates a dedicated callout block highlighting missing/required files in BOLD RED."""
        table = doc.add_table(rows=1, cols=1)
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        cell = table.cell(0, 0)
        set_cell_background(cell, "FFF5F5") # Light pink/red background
        set_cell_margins(cell, top=160, bottom=160, left=220, right=220)

        # Border styling via XML
        tcPr = cell._element.get_or_add_tcPr()
        borders = parse_xml(f'''
            <w:tcBorders {nsdecls("w")}>
                <w:left w:val="single" w:sz="36" w:space="0" w:color="FF0000"/>
                <w:top w:val="none"/>
                <w:right w:val="none"/>
                <w:bottom w:val="none"/>
            </w:tcBorders>
        ''')
        tcPr.append(borders)

        p = cell.paragraphs[0]
        p.paragraph_format.space_after = Pt(4)
        
        # File Name in RED
        r_name = p.add_run(f"★ MISSING / NEWLY IMPLEMENTED FILE: {file_name}\n")
        r_name.font.name = 'Arial'
        r_name.font.size = Pt(12)
        r_name.font.bold = True
        r_name.font.color.rgb = RGBColor(255, 0, 0) # STRICT RED #FF0000

        # Description in RED
        r_desc = p.add_run(desc_text)
        r_desc.font.name = 'Calibri'
        r_desc.font.size = Pt(10.5)
        r_desc.font.bold = True
        r_desc.font.color.rgb = RGBColor(255, 0, 0) # STRICT RED #FF0000

        doc.add_paragraph().paragraph_format.space_after = Pt(6)

    # ==========================================
    # SECTION 1: EXECUTIVE SUMMARY & AUDIT FINDINGS
    # ==========================================
    add_section_heading("1. Executive Summary & Codebase Audit Report")
    
    p = doc.add_paragraph()
    p.add_run(
        "Is project ka line-by-line deep architectural audit aur forensic analysis mukammal kar liya gaya hai. "
        "Audit ke doraan backend architecture, database queries, security vulnerabilities, aur frontend interactive flows "
        "me darj zail ahem khamiyan aur missing links samne aayein jinko 100% human-style standard ke mutabiq theek kar diya gaya hai:"
    )

    audit_table = doc.add_table(rows=1, cols=3)
    audit_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    hdr_cells = audit_table.rows[0].cells
    hdr_cells[0].text = "Module / File"
    hdr_cells[1].text = "Pehle Maujood Issue / Vulnerability"
    hdr_cells[2].text = "Applied Senior Fix & Improvement"

    for cell in hdr_cells:
        set_cell_background(cell, "047857")
        set_cell_margins(cell, top=120, bottom=120, left=150, right=150)
        p = cell.paragraphs[0]
        for r in p.runs:
            r.font.bold = True
            r.font.color.rgb = RGBColor(255, 255, 255)
            r.font.size = Pt(10)

    audit_rows = [
        ("XAMPP MySQL Engine", 
         "InnoDB Page 0 corruption (space=67, unaligned size 1777664 bytes) jiski wajah se MySQL 24 seconds baad crash ho raha tha.", 
         "Corrupt tablespace drop karke table ko cleanly recreate kiya gaya aur Aria indexes repair kiye gaye."),
        ("Bidding Query (ajax/bid.php)", 
         "SQL query me column name `amount` use kiya gaya tha jabke actual schema me `bid_amount` tha, jis se bids fail ho rahi theen.", 
         "Canonical place_bid.php controller banaya gaya jisme PDO transactions aur accurate schema columns integrate kiye."),
        ("Payment Flow (user/pay.php)", 
         "Query me `buyer_id` aur `b.amount` ke fatal column errors thay, aur standalone checkout screen missing thi.", 
         "End-to-end checkout.php implement kiya gaya aur pay.php ko iske sath link kar diya."),
        ("User Auth (register.php)", 
         "Raw string concatenation se SQL queries run ho rahi theen jo SQL Injection ka direct khatra theen.", 
         "Strict PDO Prepared Statements aur password_hash() implementation ki gayi aur CSRF validation add kiya gaya."),
        ("Alerts & Notifications", 
         "Browser ke purane block karne wale alert(), confirm(), prompt() popups use ho rahe thay.", 
         "SweetAlert2 modals aur interactive custom dialogs se 100% modernize kiya gaya.")
    ]

    for item, issue, fix in audit_rows:
        row = audit_table.add_row()
        row.cells[0].text = item
        row.cells[1].text = issue
        row.cells[2].text = fix
        for c in row.cells:
            set_cell_background(c, "FFFFFF")
            set_cell_margins(c, top=90, bottom=90, left=130, right=130)
            p = c.paragraphs[0]
            for r in p.runs:
                r.font.size = Pt(9.5)

    doc.add_paragraph().paragraph_format.space_after = Pt(10)

    # ==========================================
    # SECTION 2: MISSING & NEWLY IMPLEMENTED FILES (RED HIGHLIGHTS)
    # ==========================================
    add_section_heading("2. Newly Implemented & Missing Files (Highlighted in Red)")
    
    p = doc.add_paragraph()
    p.add_run(
        "Khaas Hidayat ke mutabiq: Project audit ke doraan jin naye files aur architectural controllers ki shadeed zaroorat thi, "
        "unko end-to-end create kiya gaya hai. Niche har file ka naam aur uski mukammal tafseel "
    )
    r_imp = p.add_run("WAZEH RED TEXT COLOR (#FF0000)")
    r_imp.font.bold = True
    r_imp.font.color.rgb = RGBColor(255, 0, 0)
    p.add_run(" me darj hai taake reader ko foran pata chal sake ke system me pehle kya missing tha:")

    # FILE 1: db.php
    add_red_file_box(
        "db.php (Project Root: /db.php)",
        "Yeh file pehle project root me missing thi. Pehle project me alag alag jagah par mysqli ke unstructured connections bikhre hue thay. "
        "Humne db.php ko create kiya jo ke Senior Full-Stack standards ke mutabiq PDO Singleton connection provide karta hai. "
        "Iske andar timing-attack safe CSRF Token generator (csrf_token(), csrf_field(), verify_csrf_token()), output XSS sanitizer sanitize(), "
        "standardized json_response() API helper, aur flash notification system shamil kiya gaya hai. "
        "Yeh poore project ko secure prepared statements ki bunyad faraham karta hai."
    )

    # FILE 2: auth_check.php
    add_red_file_box(
        "auth_check.php (Project Root: /auth_check.php)",
        "Pehle project me centralized authentication aur role verification system missing tha. Har page par alag alag session checks thay jo ke inconsistent thay. "
        "auth_check.php ek central middleware banayi gayi hai jo require_auth($role) function provide karti hai. "
        "Agar request AJAX (Fetch API) ke zariye aayi ho aur user logged in na ho to yeh JSON format me HTTP 401 response deti hai. "
        "Agar browser page visit kar raha ho to redirect parameter ke sath login.php par bhejti hai. "
        "Sath hi yeh database se user ka live status check karti hai taake suspended users foran block ho sakein."
    )

    # FILE 3: place_bid.php
    add_red_file_box(
        "place_bid.php (Project Root: /place_bid.php)",
        "Pehle live bidding ke liye koi dedicated, high-concurrency controller mojood nahi tha (ajax/bid.php me 'amount' column ka fatal bug tha aur concurrency locks missing thay). "
        "Humne place_bid.php ko create kiya jo Real-Time AJAX Bidding Engine ka markaz hai. "
        "Yeh PDO Transaction aur Pessimistic Row Lock (SELECT ... FOR UPDATE) use karta hai taake do users ek hi second me race condition na paida karein. "
        "Isme Anti-Sniping logic shamil hai: Agar auction khatam hone me aakhri 3 minute (180 seconds) bache hon aur bid aaye, to auction khud-ba-khud 3 minute mazeed extend ho jati hai. "
        "Iske ilawa auto-bids table se proxy counter-bids khud evaluate karta hai aur seller wa outbid users ko real-time notifications send karta hai."
    )

    # FILE 4: get_live_bid.php
    add_red_file_box(
        "get_live_bid.php (Project Root: /get_live_bid.php)",
        "Client-side JavaScript ke liye ek dedicated lightweight polling endpoint missing tha jo bina page reload kiye live auction ka snapshot de sake. "
        "get_live_bid.php create kiya gaya hai jo har 2-3 second baad call hota hai. "
        "Yeh current highest price, bidder ka masked name, avatar, remaining countdown seconds, aur total bids return karta hai. "
        "Sab se ahem feature: Jaise hi countdown timer 0 hota hai, yeh script background me auction ko status='ended' karti hai, "
        "winner declare karti hai, aur winner ko 'can_checkout: true' ke sath checkout.php ka secure button provide karti hai."
    )

    # FILE 5: checkout.php
    add_red_file_box(
        "checkout.php (Project Root: /checkout.php)",
        "Poore project me auction jeetne ke baad purchase karne aur order place karne ka complete checkout flow ghayab (missing) tha. "
        "Humne checkout.php ko mukammal taur par Bootstrap 5, interactive forms, aur PDO transaction engine ke sath banaya hai. "
        "Access Control: Sirf wahi user is page ko access kar sakta hai jo officially auction ka winning bidder ho. "
        "Features: Winning Hammer Price, 2% platform protection fee, aur shipping logistics breakdown. "
        "Shipping Address Form: Recipient Name, Phone, Delivery Address, City, Postal Code. "
        "Payment Options: Platform Wallet Balance (auto-deduction with transactions table entry), Cash on Delivery (COD) / Direct Escrow, "
        "Direct Bank Transfer, aur Debit/Credit Card. "
        "Order Finalization: Form submit hone par orders aur payments table me secure insertion hoti hai, item status 'sold' hota hai, "
        "aur printable official receipt generate hoti hai."
    )

    # FILE 6: notifications.php
    add_red_file_box(
        "notifications.php (Project Root: /notifications.php)",
        "Navbar ke notification bell icon par click karne par root level par notifications.php missing thi jis se 404 error aata tha. "
        "Humne root notifications.php ko create kiya jo user ke tamam alerts (New Bids, Outbid warnings, Won Auctions, Orders) ko sleek cards me dikhata hai. "
        "Isme AJAX powered 'Mark as Read', 'Mark All Read', aur Category Tabs (Bids, Orders, Unread) shamil kiye gaye hain."
    )

    # FILE 7: user/messages.php
    add_red_file_box(
        "user/messages.php (User Subdirectory: /user/messages.php)",
        "Pehle user dashboard, profile ya notification dropdown se jab koi user kisi message alert par click karta tha "
        "to relative path ki wajah se browser 'user/messages.php?user_id=...' ko request karta tha aur Apache 404 Not Found error return karta tha. "
        "Humne user/messages.php router create kiya jo request ke tamam query parameters (user_id, item_id wagera) ko mehfooz rakhte hue "
        "user ko smoothly canonical ../messages.php par redirect karta hai. Sath hi header.php ke handleNotificationClick() me bhi automatic root prefixing implement kar di gayi hai."
    )

    # FILE 8: user/notifications.php
    add_red_file_box(
        "user/notifications.php (User Subdirectory: /user/notifications.php)",
        "User panel aur header dropdown se pehle notifications par click karne par ek purani basic unstyled table khulti thi jo naye filters aur mark-as-read ko support nahi karti thi. "
        "Humne user/notifications.php ko canonical ../notifications.php par smoothly redirect karne wale router me convert kar diya hai aur header dropdown me direct root path link set kar diya hai."
    )

    # ==========================================
    # SECTION 3: CORE MODULE ARCHITECTURE
    # ==========================================
    add_section_heading("3. Core Modules Deep Architectural Breakdown")

    # Module A: Authentication
    add_section_heading("A. User Registration & Role-Based Authentication Flow", level=2)
    p = doc.add_paragraph()
    p.add_run(
        "Authentication flow ko dynamic role-based banaya gaya hai jisme Buyer, Seller aur Admin roles strictly segregated hain:\n"
        "• Registration (register.php): Form me Full Name, Email, Phone, Gender, Password, aur Role selection (Buyer ya Seller) mojud hai. "
        "Server-side par PDO prepared statement se duplicate email check hoti hai aur password ko password_hash(PASSWORD_DEFAULT) se encrypt kiya jata hai. "
        "Client side par SweetAlert2 validation lagayi gayi hai jo password mismatch hone par smooth red popup show karti hai.\n"
        "• Login Engine (login.php): Email aur password ko fetch karke password_verify() ke zariye validate kiya jata hai. "
        "Session me uniform keys set ki jati hain: $_SESSION['user_id'], $_SESSION['role'], $_SESSION['user_name'], $_SESSION['user_email']. "
        "Agar 'Remember Me' select kiya jaye to 30-din ka cryptographically random sha256 token cookie me store hota hai jo auto-login provide karta hai.\n"
        "• Role Redirection: Admin user login karte hi admin/dashboard.php par jata hai jabke Buyer/Seller user/dashboard.php par smoothly redirect hotay hain."
    )

    # Module B: Product Addition
    add_section_heading("B. Seller Product Entry & Secure Multi-Image Upload System", level=2)
    p = doc.add_paragraph()
    p.add_run(
        "Seller side product creation (/user/add_items.php) me mukammal audit karke darj zail checks enforce kiye gaye hain:\n"
        "• Form Fields: Item Title (5-255 chars), Category selection, Detailed Description, Starting Price, Reserve Price, "
        "Minimum Bid Increment, City/Area, Start Date/Time, aur End Date/Time.\n"
        "• Image Upload Security: File uploads par sirf extension nahi balke server-side mime_content_type() function se binary MIME verification "
        "ki jati hai (image/jpeg, image/png, image/webp). Har image ka unique random filename (item_timestamp_uniqid.ext) generate hota hai "
        "taake directory traversal ya script execution ka koi chance na rahe. Max file size 5MB aur max 5 images allowed hain.\n"
        "• Database Integrity: Item insert hote hi unique lot code (AUC-XXXXXX) assign hota hai, status='active' set hota hai, "
        "aur primary image ke sath sath baki images JSON format me images column aur item_images table me store hoti hain."
    )

    # Module C: Real-Time Bidding Engine
    add_section_heading("C. Real-Time Bidding Engine & Anti-Sniping Protection", level=2)
    p = doc.add_paragraph()
    p.add_run(
        "Bidding engine (/place_bid.php aur /get_live_bid.php) system ka sab se sensitive hissa hai. Iska workflow mandarja zail hai:\n"
        "1. Instant AJAX Submission: Frontend JavaScript bidForm submit hone par Fetch API ke zariye place_bid.php ko POST call bhejta hai. "
        "Page reload bilkul nahi hota.\n"
        "2. Row Locking (Pessimistic): MySQL InnoDB table par `SELECT ... FOR UPDATE` lock lagaya jata hai taake do mukhtalif buyers "
        "ek hi waqt me conflicting price place na kar sakein.\n"
        "3. Strict Validation Pipeline:\n"
        "   - Bid Amount must be >= (Current Price + Minimum Increment).\n"
        "   - Seller apni hi listing par bid nahi laga sakta.\n"
        "   - Current highest bidder apne hi bid ke khilaf dobara bid nahi laga sakta.\n"
        "   - Expired ya closed auction par bid reject hoti hai.\n"
        "4. Anti-Sniping Feature: Bidders aksar aakhri second me bid lagate hain (sniping). Hamara system aakhri 180 seconds me aane wali har valid bid par "
        "auction end time me 3 minutes ka izafa kar deta hai aur extended_time record karta hai.\n"
        "5. Proxy Auto-Bidding: Agar kisi doosre user ne auto_bids table me max limit set kar rakhi ho, to system foran unki taraf se counter-bid generate karta hai.\n"
        "6. In-App Notifications: Pehle wale bidder ko 'You Have Been Outbid!' ka alert jata hai aur seller ko 'New Bid Placed' ka notification milta hai."
    )

    # Module D: Auction Closing & Checkout
    add_section_heading("D. Auction Closing, Winner Declaration & End-to-End Checkout", level=2)
    p = doc.add_paragraph()
    p.add_run(
        "Auction khatam hone par system ka automatic checkout aur order fulfillment flow is tarah execute hota hai:\n"
        "1. Auto-Finalization: Countdown timer 0 hote hi get_live_bid.php status ko 'ended' mark karta hai, "
        "highest bidder ko items.winner_id aur items.winning_bid_id assign karta hai, aur bids table me winning bid ko is_winning=1, bid_status='won' set karta hai.\n"
        "2. Winner-Only Checkout Access: live_auction.php par sirf verified winner ko green congratulatory card ke sath "
        "'Proceed to Checkout' button nazar aata hai. Agar koi aur user direct URL khole to checkout.php use access denied ka card dikhata hai.\n"
        "3. Complete Order & Payment Flow (/checkout.php):\n"
        "   - Price Breakdown: Winning Hammer Price + 2% Platform Marketplace Protection Fee + Insured Shipping Logistics Fee.\n"
        "   - Shipping Form: Recipient Name, Contact Number, Full Delivery Address, City, Postal Code.\n"
        "   - Multiple Payment Options: Account Wallet (instant deduction), Cash on Delivery (COD) / Escrow, Bank Wire Transfer, Credit/Debit Card.\n"
        "   - Database Transaction: orders table me order_number (ORD-YYYYMMDD-XXXXXX) insert hota hai, payments table me invoice_no generate hoti hai, "
        "item status 'sold' mark ho jata hai, aur buyer wa seller dono ko instant confirmations mil jati hain."
    )

    # ==========================================
    # SECTION 4: DATABASE ARCHITECTURE
    # ==========================================
    add_section_heading("4. Complete MySQL Database Schema & Relationship Mapping")
    p = doc.add_paragraph()
    p.add_run(
        "Database schema (/database/schema.sql) ko fully relational, third-normal-form (3NF), aur foreign key constraints ke sath organize kiya gaya hai:"
    )

    schema_table = doc.add_table(rows=1, cols=3)
    schema_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    s_hdr = schema_table.rows[0].cells
    s_hdr[0].text = "Table Name"
    s_hdr[1].text = "Key Columns & Datatypes"
    s_hdr[2].text = "Relational Constraints & Role"

    for cell in s_hdr:
        set_cell_background(cell, "047857")
        set_cell_margins(cell, top=100, bottom=100, left=140, right=140)
        p = cell.paragraphs[0]
        for r in p.runs:
            r.font.bold = True
            r.font.color.rgb = RGBColor(255, 255, 255)
            r.font.size = Pt(9.5)

    s_rows = [
        ("users", "id, name, email, password, role, status, balance, remember_token", "Core user entities (buyer, seller, admin). Has wallet balance and tokens."),
        ("categories", "id, name, slug, icon, sort_order, status", "Product categorization hierarchy."),
        ("items", "id, auction_code, title, starting_price, current_price, seller_id, winner_id, status", "Central auction lot records. Foreign keys to users (seller, winner)."),
        ("bids", "id, item_id, user_id, bid_amount, bid_status, is_winning, is_auto_bid", "Immutable bid ledger. Correct column name `bid_amount`. Links items and users."),
        ("auto_bids", "id, item_id, user_id, max_bid_amount, is_active", "Proxy auto-bidding rules set by bidders. Unique index on (item_id, user_id)."),
        ("orders", "id, order_number, auction_id, buyer_id, seller_id, hammer_price, total_amount, shipping_status", "Auction purchase orders. Links buyer, seller, item, and delivery details."),
        ("payments", "id, invoice_no, user_id, item_id, bid_id, amount, payment_method, payment_status", "Settlement audit records with transaction IDs and billing records."),
        ("notifications", "id, user_id, type, title, message, link, is_read", "Real-time in-app user notifications for bids, outbids, and sales."),
        ("messages", "id, sender_id, receiver_id, item_id, message, is_read", "Direct chat conversations between buyers and sellers.")
    ]

    for tbl, cols, rel in s_rows:
        row = schema_table.add_row()
        row.cells[0].text = tbl
        row.cells[1].text = cols
        row.cells[2].text = rel
        for c in row.cells:
            set_cell_background(c, "FFFFFF")
            set_cell_margins(c, top=80, bottom=80, left=120, right=120)
            p = c.paragraphs[0]
            for r in p.runs:
                r.font.size = Pt(9)

    doc.add_paragraph().paragraph_format.space_after = Pt(12)

    # ==========================================
    # SECTION 5: SECURITY AUDIT & BEST PRACTICES
    # ==========================================
    add_section_heading("5. Security Architecture & Hardening Standards")
    p = doc.add_paragraph()
    p.add_run(
        "Is system me production-grade security patterns implement kiye gaye hain:\n"
        "1. Strict SQL Injection Prevention: Tamam new endpoints (db.php, auth_check.php, place_bid.php, get_live_bid.php, checkout.php, register.php, login.php) "
        "me 100% PDO prepared statements use kiye gaye hain with parameter binding (`:param`). Kisi jagah user input string concatenation nahi hoti.\n"
        "2. Cross-Site Scripting (XSS) Sanitization: Har frontend output par `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` lazmi render hota hai. "
        "db.php me sanitize() aur e() helpers faraham kiye gaye hain.\n"
        "3. Cryptographic Password Security: Passwords ko bcrypt (PASSWORD_DEFAULT) hashing se secure kiya gaya hai aur password_verify() se check hota hai.\n"
        "4. CSRF Protection: Timing-attack safe hash_equals() validation ke sath CSRF tokens generate aur verify kiye jate hain.\n"
        "5. Concurrency Race Condition Safety: Bidding aur payments dono me database transactions (beginTransaction, commit, rollBack) "
        "aur pessimistic row locking (`FOR UPDATE`) utilize ki gayi hai."
    )

    # ==========================================
    # SECTION 6: OPERATIONAL TESTING INSTRUCTIONS
    # ==========================================
    add_section_heading("6. Operational Testing & Verification Guide")
    p = doc.add_paragraph()
    p.add_run(
        "System ko live test karne ke aasan tareeqay mandarja zail hain:\n"
        "1. XAMPP Services: XAMPP Control Panel kholein aur Apache wa MySQL dono ko 'Start' karein (dono green hone chahiye).\n"
        "2. User Login / Registration: Browser me `http://localhost/geo-auction-system-GIT/register.php` par ja kar Buyer ya Seller account banayein.\n"
        "3. Place a Live Bid: `http://localhost/geo-auction-system-GIT/live_auction.php` par kisi bhi active lot par bid submit karein. "
        "Bina page reload kiye live green success popup aayega aur latest price update ho jayegi.\n"
        "4. Auto Closing & Checkout: Jab countdown timer 0 hoga, winner ko screen par 'Congratulations! You Won' ka banner aur "
        "'Proceed to Checkout' button nazar aayega. Us par click karke `checkout.php` me shipping address daalein aur payment method select karke order confirm karein.\n"
        "5. Notifications Center: Header bell icon par click karke `notifications.php` me tamam real-time notifications check karein."
    )

    # Save Document
    output_filename = "Geo_Auction_System_Project_Manual.docx"
    doc.save(output_filename)
    print(f"Document successfully created: {os.path.abspath(output_filename)}")

if __name__ == "__main__":
    create_manual()
