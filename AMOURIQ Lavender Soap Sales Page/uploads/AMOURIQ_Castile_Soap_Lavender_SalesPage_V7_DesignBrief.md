# AMOURIQ ORGANIC OLIVE CASTILE SOAP — LAVENDER
## SALES PAGE — V7 · DESIGN HANDOFF BRIEF (สำหรับ Claude Design)

---

## 📝 เปลี่ยนแปลงจาก V6 → V7

| # | จุดที่แก้ | รายละเอียด |
|---|---|---|
| 1 | ตัด **Section 10 — REAL SOAP QUALITY PANEL** ออกทั้งหมด | ลดความหนาแน่นเชิงเทคนิค ตัวเลข Free Alkali ยังคงอยู่ครบใน Section 09 (เนื้อหาหลักไม่หาย) |
| 2 | **เรียงลำดับ section ใหม่** | จาก 24 section เหลือ 23 section — เลขที่ตั้งแต่ Section 10 เป็นต้นไปขยับขึ้น 1 ลำดับทั้งหมด |
| 3 | **Section 19 — เติมราคา/ขนาดจริง** | ดึงจาก `Pricing/Castile soap price.pdf` — 3 ขนาด (100/250/500 ML) ราคาเท่ากันทุกกลิ่น (Lavender / Rosemary / Rose Geranium) |
| 4 | **เพิ่มส่วน Design System Reference + Assets Available** ที่ด้านบนไฟล์นี้ | ให้ Claude Design ใช้สร้างหน้าเว็บได้ครบโดยไม่ต้องเปิดไฟล์อื่นเพิ่ม |

⚠️ **พบ 1 จุดที่ต้องเช็คกับทีม:** SKU code ขนาด 250 ML ของ Lavender ในไฟล์ pricing เขียนว่า `SL-CT-OR-LV0150-1` ขณะที่ Rosemary/Rose Geranium ใช้ pattern `RM0250-1` / `RG0250-1` — น่าจะเป็น typo (ควรเป็น `LV0250-1`) นกไม่ได้แก้เองเพราะไม่ใช่ข้อมูลที่ตัดสินใจแทนได้ ฝากคุณวราห์ยืนยันกับทีมก่อนขึ้นเว็บจริง

---

## 🎨 DESIGN SYSTEM REFERENCE — AMOURIQ

> สรุปจาก `brand.md` Section 8 (Visual Brand) — ใช้ค่านี้ตรงๆ ห้ามเดาสีหรือฟอนต์เอง

### Colors
| ชื่อ | Hex | บทบาทในหน้านี้ |
|---|---|---|
| Milk Oat | `#F5EFE4` | พื้นหลัง card / hero section |
| Olive Grove | `#4A5B32` | พื้นหลัง break section เข้ม (เช่น Section 03 "But Real Soap Is Not Enough", Section 16 "Evidence") |
| Sunbaked Clay | `#C1673F` | ปุ่ม CTA หลักทั้งหมด ("เลือกขนาดและสั่งซื้อ", "เพิ่มลงตะกร้า") |
| Roasted Umber | `#3A322A` | ตัวหนังสือหลัก, Footer |
| Sage Mist Teal | `#4F6D63` | ไอคอนสถิติ (Moisture/Water Loss/Barrier), CTA รอง, accent เนื้อหาความชุ่มชื้น |
| Web Light | `#FBF8F2` | พื้นหลังเต็มหน้าเว็บ (ไม่ใช่ Milk Oat) |

Accessibility ผ่านแล้ว: Roasted Umber บน Milk Oat (10.99:1, AAA) · Olive Grove บน Milk Oat (6.47:1, AA) · Sage Mist Teal บน Milk Oat (4.96:1, AA) · ตัวหนังสือขาวบนปุ่ม Sunbaked Clay (3.98:1 — ใช้ได้เฉพาะตัวใหญ่ ≥18px)

### Typography
- Thai Heading + Body: **Prompt**
- English Heading: **DM Sans (Bold/700)**
- English Body: **DM Sans (Regular/400–500)**
- ห้ามใช้ Archivo Black (ถอดออกจากระบบแล้ว)

### Photography Style
อบอุ่น จับต้องได้ · แสงธรรมชาติ ไม่ soft-focus · โทนครีม/เขียวมะกอก/terracotta · ไม่ studio โฆษณา ไม่สปาคลิเช่ (ห้าม lotus/ไผ่/มณฑล) · มือ/ลำคอ/ผมสัมผัสสินค้าจริง ไม่ใช่ภาพขวดลอยเดี่ยวกลางเฟรมขาว

### Logo & Tagline
- Logo lockup: ไอคอนหยดน้ำ + ชื่อแบรนด์ — ไฟล์จริงอยู่ที่ `Logo/Logo Amouriq #3A322A.png` (สำหรับพื้นสว่าง) และ `Logo/Logo Amouriq #FBF8F2.png` (สำหรับพื้นเข้ม)
- Official Tagline (**ห้ามแก้คำ**): "AMOURIQ Thoughtful Botanical Care" / "อมอริค ใส่ใจ ด้วยธรรมชาติที่คัดสรร"

### ⚡ Visual Precedent ที่มีอยู่แล้ว
มีงาน Claude Design ของ AMOURIQ ทำไว้ก่อนหน้านี้แล้ว (IG Carousel เรื่อง Organic Certification) ให้ใช้เป็น reference โทน/เลย์เอาต์ตรงๆ:
- `Claude Design/AMOURIQ.pdf`, `Claude Design/AMOURIQ-ปรับ margin.pdf`, `Claude Design/AMOURIQ+ภาพ.pdf`
- `Claude Design/PNG export/01-cover.png` ถึง `04-certified.png`

จากตัวอย่างนี้: หัวเรื่องใหญ่ตัวหนา (Prompt Bold) ชิดซ้าย, โลโก้บนซ้าย + page indicator บนขวา, eyebrow label สีเน้น (Sunbaked Clay), สลับพื้นหลัง Milk Oat (เนื้อหาอ่านง่าย) กับ Olive Grove (break section), ภาพถ่ายจริง full-width ด้านล่างการ์ด — **ให้ Sales Page นี้ใช้ภาษาภาพเดียวกัน** เพื่อความต่อเนื่องของแบรนด์

---

## 🖼️ ASSETS AVAILABLE (ไฟล์จริง ใช้แทน placeholder ได้ทันที)

| ไฟล์ | ใช้ตรงไหน |
|---|---|
| `Logo/Logo Amouriq #3A322A.png` | Header/Footer บนพื้นสว่าง |
| `Logo/Logo Amouriq #FBF8F2.png` | Header/Footer บนพื้นเข้ม (Olive Grove section) |
| `Product review/Castile soap/Castile soap assorted.png` | ภาพสินค้าจริงทั้ง 3 กลิ่นวางคู่กัน — เหมาะกับ Section 19 (เดิม 20) Buying Block หรือ Section 02 |
| `Product review/Castile soap/Castile soap rose geranium 30ml.png`, `rosemary 100ml.png` | packshot อ้างอิงสี/รูปทรงขวดสำหรับทีมภาพ (Lavender ยังไม่มี packshot แยก — ใช้ทรงขวด/label เดียวกันแทนสีตาม variant color-coding: Lavender = Sage Mist Teal ตาม brand.md) |
| `Claude Design/PNG export/*.png` | Reference โทนภาพ/เลย์เอาต์ที่อนุมัติแล้วก่อนหน้า |

**ยังขาด:** ภาพ hero (มือ/แขนใช้สินค้าจริงในห้องน้ำแสงธรรมชาติ), packshot Lavender โดยเฉพาะ, ภาพ lifestyle สำหรับ Section 15 (เดิม 16, The Ritual) และ Section 22 (เดิม 23, Final Close) — ต้องถ่ายเพิ่มหรือ generate ตาม Photography Style ด้านบน

---

## 📄 PAGE CONTENT (V7 — เรียงลำดับใหม่ 23 Section)

### 00 — TOP TRUST BAR
**100% REAL SOAP†**　·　**DermX Tested**　·　**Thai FDA Notified**　·　**Thoughtfully Made for Skin**
> Visual: แถบบางด้านบน พื้น Web Light ตัวหนังสือ Roasted Umber ไม่ทำเป็น promotional bar แบบ SALE

---

### 01 — HERO
**AMOURIQ ORGANIC OLIVE CASTILE SOAP — LAVENDER**

**100% REAL SOAP†** — สบู่เหลวแท้ ตามนิยาม มอก. เอส 14-2562

# สบู่เหลวแท้ ที่ไม่ได้คิดถึงแค่ความสะอาด

Organic Olive Castile Soap ที่ AMOURIQ พัฒนาขึ้นโดยมุ่งการดูแลผิวโดยเฉพาะ พร้อมผลการประเมินในอาสาสมัครโดย DermX คณะเภสัชศาสตร์ มหาวิทยาลัยเชียงใหม่ เพื่อดูว่า **หลังล้างแล้ว เกิดอะไรขึ้นกับความชุ่มชื้นและการสูญเสียน้ำของผิว**

**3 Proof Cards:**
- MOISTURE ↑ **+13.37%** ความชุ่มชื้นเพิ่มขึ้นสูงสุด หลังใช้ 4 ชั่วโมง*
- WATER LOSS ↓ **−10.67%** ค่าการสูญเสียน้ำจากผิว หลังใช้ 8 ชั่วโมง**
- BARRIER-FRIENDLY ผลการทดสอบสรุปว่า **ไม่พบผลทำลายเกราะป้องกันผิว**

**[ เลือกขนาดและสั่งซื้อ ]** (ปุ่ม Sunbaked Clay) · `ดูผลการทดสอบ ↓` (text link)

> *ความชุ่มชื้นหลัง 1, 4 และ 8 ชั่วโมงเพิ่มขึ้นจากก่อนใช้อย่างมีนัยสำคัญทางสถิติที่ระดับความเชื่อมั่น 95%
> **ค่า TEWL หลัง 8 ชั่วโมงต่ำกว่าก่อนใช้ 10.67%; ความแตกต่างของ TEWL เมื่อเทียบก่อนใช้ไม่ถึงนัยสำคัญทางสถิติในการศึกษานี้
> ทดสอบในอาสาสมัครสุขภาพดี 20 คน
> **†** จัดตามเกณฑ์เคมีของนิยาม "สบู่เหลวแท้" ในมาตรฐานอุตสาหกรรมเอส มอก. เอส 14-2562 ซึ่ง AMOURIQ เลือกใช้เป็นเกณฑ์อ้างอิงคุณภาพภายในของเราเอง ไม่ใช่การรับรองหรือขึ้นทะเบียนตามมาตรฐานดังกล่าว

**Visual:** Layout 55:45 — ซ้าย: ภาพมือ/แขนใช้สินค้าจริงในห้องน้ำแสงธรรมชาติ (ยังขาดภาพ ดู Assets Available) ขวา: copy + 3 proof cards พื้น Milk Oat ไอคอนสถิติสี Sage Mist Teal ไม่ใช้ภาพขวดลอยกลางพื้นขาวแบบ luxury ad

---

### 02 — THE MEMORY LOCK
# หลังล้าง สิ่งที่เกิดขึ้นกับผิวสำคัญไม่แพ้ความสะอาด

3 คอลัมน์ typographic ใหญ่: **MOISTURE ↑** ผิวชุ่มชื้นเพิ่มขึ้น · **WATER LOSS ↓** การสูญเสียน้ำจากผิวลดลง · **BARRIER-FRIENDLY** เป็นมิตรต่อเกราะป้องกันผิว

> เพราะผลิตภัณฑ์ทำความสะอาดที่ใช้ทุกวัน ไม่ควรจบหน้าที่เพียงตอนที่ล้างออก

**Visual:** พื้น Milk Oat ไม่มี icon skincare generic

---

### 03 — WHY "REAL SOAP" MATTERS
**100% REAL SOAP†**

# สิ่งที่เรียกว่า "สบู่เหลว" ไม่ได้เป็นสบู่เหลวแท้เหมือนกันทั้งหมด

มาตรฐานอุตสาหกรรมเอส **มอก. เอส 14-2562** แยกคำว่า **"สบู่เหลวแท้"** และ **"สบู่เหลวผสม"** ออกจากกันอย่างชัดเจน — สบู่เหลวแท้มีเกลือของกรดไขมันจากน้ำมันหรือไขมันเป็นองค์ประกอบสำคัญ ส่วนสบู่เหลวผสมมีสารลดแรงตึงผิวสังเคราะห์ร่วมด้วย (ข้อ 3.3.1: สบู่เหลวแท้ต้องไม่พบสารลดแรงตึงผิวสังเคราะห์)

# สำหรับ AMOURIQ คำว่า Real Soap ไม่ใช่คำที่ใช้เพื่อให้สินค้าฟังดูเป็นธรรมชาติขึ้น

แต่เป็น product identity ที่มีนิยามให้ตรวจสอบได้ — เราหยิบนิยามทางเคมีของคำว่า "สบู่เหลวแท้" จาก มอก. เอส 14-2562 มาใช้เป็นเกณฑ์วัดคุณภาพของเราเอง เพราะตรงกับสิ่งที่เราอยากพิสูจน์ให้เห็นมากที่สุด

**[ ดูนิยามที่เราอ้างอิง ]**

**Visual:** พื้น Olive Grove (dark break section) ตัวหนังสือสีขาว/Web Light

---

### 04 — BUT REAL SOAP IS NOT ENOUGH
# แต่สำหรับเรา การเป็น "สบู่เหลวแท้" ยังไม่พอ

## แล้วหลังล้าง ผิวเป็นอย่างไร?

AMOURIQ ไม่ได้หยุดอยู่ที่วัตถุดิบ สูตร หรือคำว่า Castile เราเลือกทดสอบ **ผลิตภัณฑ์สำเร็จรูปบนผิวจริง**

**Visual:** พื้นเปลี่ยนกลับเป็น Olive Grove Grove (transition), เตรียมส่ง visual ต่อไปยัง section 05

---

### 05 — SKIN RESULT 1: MOISTURE ↑
# ผิวชุ่มชื้น "เพิ่มขึ้น" — วัดด้วยเครื่อง Corneometer®

| ช่วงเวลา | การเปลี่ยนแปลงความชุ่มชื้น |
|---|---:|
| หลังใช้ทันที | +7.42% |
| หลัง 1 ชั่วโมง | **+8.48%*** |
| หลัง 4 ชั่วโมง | **+13.37%*** |
| หลัง 8 ชั่วโมง | **+9.27%*** |

# สูงสุด +13.37% หลังใช้ 4 ชั่วโมง

ผลหลัง 1, 4 และ 8 ชั่วโมงแตกต่างจากก่อนใช้อย่างมีนัยสำคัญทางสถิติที่ระดับความเชื่อมั่น 95%

> สบู่ที่ดีสำหรับ AMOURIQ จึงไม่ควรจบหน้าที่แค่ตอนที่ล้างออก

**Visual:** กราฟ reconstructed จากข้อมูลจริง ไม่ screenshot รายงานตรงๆ — highlight แท่ง 4h และ 8h ด้วยสี Sage Mist Teal

---

### 06 — SKIN RESULT 2: WATER LOSS ↓
# ผิวสูญเสียน้ำน้อยลง (TEWL) — วัดด้วย Tewameter®

| ช่วงเวลา | การเปลี่ยนแปลง TEWL |
|---|---:|
| หลังใช้ทันที | +0.81% |
| หลัง 1 ชั่วโมง | **−5.34%** |
| หลัง 4 ชั่วโมง | **−3.60%** |
| หลัง 8 ชั่วโมง | **−10.67%** |

# หลัง 8 ชั่วโมง ค่า TEWL ต่ำกว่าก่อนใช้ 10.67%

> **Evidence note:** การเปลี่ยนแปลงค่า TEWL เมื่อเทียบกับก่อนใช้ไม่แตกต่างอย่างมีนัยสำคัญทางสถิติในการศึกษานี้ AMOURIQ จึงไม่สื่อว่า "ลด TEWL อย่างมีนัยสำคัญ" — ความน่าเชื่อถือสำคัญกว่าการทำตัวเลขให้ฟังดูแรงกว่าหลักฐาน

**Visual:** กราฟรูปแบบเดียวกับ Section 05 เพื่อความต่อเนื่อง

---

### 07 — SKIN RESULT 3: BARRIER-FRIENDLY
# เป็นมิตรต่อเกราะป้องกันผิว

ค่า TEWL หลังใช้ **ไม่แตกต่างจากก่อนใช้อย่างมีนัยสำคัญทางสถิติ** → รายงานสรุปว่า **ผลิตภัณฑ์ไม่พบผลทำลายเกราะป้องกันผิว** และช่วยรักษาความชุ่มชื้นไว้ได้จนถึงช่วงประเมิน 8 ชั่วโมง

เราจึงเลือกใช้คำว่า **BARRIER-FRIENDLY** ไม่ใช่ "Repair/Restore/Rebuild Skin Barrier" เพราะผลที่มีไม่ได้ทดสอบ claims เหล่านั้น

---

### 08 — MEMORY LOCK #2
# 100% REAL SOAP† ที่คิดถึงสิ่งที่เกิดขึ้นกับผิวหลังล้าง

**MOISTURE ↑** ชุ่มชื้นเพิ่มขึ้น · **WATER LOSS ↓** สูญเสียน้ำน้อยลง · **BARRIER-FRIENDLY** เป็นมิตรต่อเกราะป้องกันผิว

---

### 09 — QUALITY PROOF: FREE ALKALI NOT DETECTED
**REAL SOAP QUALITY‡**

# มี KOH ในกระบวนการทำสบู่ แต่ผลตรวจไม่พบด่างอิสระในสบู่สำเร็จรูป

Potassium Hydroxide (KOH) เป็นส่วนหนึ่งของสูตรที่ใช้ในการทำสบู่ AMOURIQ แต่คำถามที่สำคัญกว่าคือ **หลังผลิตเสร็จ มีด่างอิสระเหลืออยู่หรือไม่?**

# FREE ALKALI: NOT DETECTED

เทียบกับเกณฑ์สบู่เหลวแท้ตาม มอก. เอส 14-2562 (ด่างอิสระไม่เกิน 0.05% โดยมวล) ผลของ AMOURIQ ระบุว่า **Not Detect**

> คุณภาพของกระบวนการ ควรมีสิ่งที่ตรวจสอบได้ในผลิตภัณฑ์สำเร็จรูป

> **‡** ผลทดสอบเคมีพื้นฐานของเนื้อสบู่ (ด่างอิสระ) ทดสอบจากสูตรเนื้อสบู่พื้นฐานเดียวกันที่ใช้ผลิต Organic Olive Castile Soap ทุกกลิ่น (สุ่มตัวอย่างจากแบทช์กลิ่น Rosemary) ก่อนแบ่งเติมน้ำมันหอมระเหยแยกตามกลิ่นในขั้นตอนสุดท้าย

**Visual:** ตัวเลข "NOT DETECTED" เป็นจุดโฟกัสสายตาใหญ่ที่สุดในหน้า ใช้สี Olive Grove หรือ Sage Mist Teal เป็น accent

---

### 10 — FINISHED-SOAP PROOF *(เดิม Section 11)*
**FINISHED-SOAP ANALYSIS**

# เราไม่ได้หยุดตรวจแค่วัตถุดิบ แต่ตรวจสิ่งที่อยู่ในสบู่หลังผลิตเสร็จจริง

## เมื่อผ่านกระบวนการทำสบู่แล้ว ผลิตภัณฑ์สำเร็จรูปตรวจพบอะไรบ้าง?

- **NATURAL SQUALENE** — DETECTED — ตรวจพบในผลิตภัณฑ์สำเร็จรูป กลิ่น Lavender — ทดสอบโดยมหาวิทยาลัยรังสิต
- **BETA-CAROTENE** — DETECTED — ตรวจพบในผลิตภัณฑ์สำเร็จรูป กลิ่น Lavender — ทดสอบโดยมหาวิทยาลัยรังสิต
- **GLYCERINE‡** — DETECTED — ตรวจพบในผลิตภัณฑ์สำเร็จรูป — ทดสอบโดย วว. / TISTR
- **ALPHA-TOCOPHEROL‡** — DETECTED — ตรวจพบในผลิตภัณฑ์สำเร็จรูป — ทดสอบโดย วว. / TISTR

Squalene และ Beta-carotene ทดสอบจากตัวอย่างกลิ่น Lavender โดยตรง ส่วน Glycerine และ Alpha-tocopherol‡ ทดสอบจากสูตรเนื้อสบู่พื้นฐานร่วม (ดูหมายเหตุ ‡ ใน Section 09)

**Visual:** 4 การ์ดเรียงแนวนอน/grid 2x2 พื้น Milk Oat

---

### 11 — THE PROOF BRIDGE *(เดิม Section 12)*
# แต่การตรวจพบสิ่งที่อยู่ในสบู่ ยังไม่ใช่คำตอบสุดท้าย

**WHAT'S IN THE FINISHED SOAP?** Squalene · Beta-carotene · Glycerine · Alpha-tocopherol
**WHAT HAPPENS ON SKIN?** Moisture ↑ · Water Loss ↓ · Barrier-Friendly

> เราไม่จำเป็นต้องเดาว่าสารตัวไหนทำให้เกิดผลลัพธ์ใด เพราะเราทดสอบผลิตภัณฑ์สำเร็จรูปทั้งสูตรกับผิวจริง

**Visual:** 2 คอลัมน์เทียบกัน เชื่อมด้วยลูกศรหรือเส้นตรงกลาง — นี่คือ key message ที่ควรเด่นที่สุดในหน้า

---

### 12 — FROM BOTANICAL OILS TO FINISHED SOAP *(เดิม Section 13)*
# เริ่มจากน้ำมันที่คัดสรร แล้วตรวจสิ่งที่เราทำออกมาจริง

**Flow infographic 4 ขั้น (ใช้เป็น infographic หลักของหน้า):**
1. **WHAT WE START WITH:** Organic Olive Oil · Coconut Oil · Castor Oil · Jojoba Oil · Palm/Palm Kernel Oil · Cocoa Butter (+ Lavender/Lavandin Oils) — มาตรฐาน: COSMOS ECOCERT (Olive, สเปน), IFOAM (Coconut), USDA (Castor, Jojoba), RSPO (Palm-derived)
2. **SOAP-MAKING PROCESS:** เกือบ 3 ปีในการพัฒนาสูตร · ควบคุมกระบวนการอย่างพิถีพิถัน · บ่มไม่น้อยกว่า 72 ชั่วโมง
3. **WHAT WE VERIFY AFTERWARD:** Free Alkali‡ — NOT DETECTED · Squalene · Beta-carotene (กลิ่น Lavender) · Glycerine‡ · Alpha-tocopherol‡
4. **WHAT WE TEST ON SKIN:** Moisture ↑ · Water Loss ↓ · Barrier-Friendly

**Visual:** แนวตั้ง 4 บล็อกเชื่อมด้วยลูกศร ↓ พื้น Milk Oat สลับ Web Light

---

### 13 — WHY AMOURIQ MADE IT THIS WAY *(เดิม Section 14)*
# หนึ่งเป้าหมาย: ผิวของคุณ

> เราไม่ได้ต้องการสร้างสบู่ที่พยายามทำทุกอย่าง แต่ต้องการพัฒนา Castile Soap สำหรับการดูแลผิว

**เกือบ 3 ปี** Development · **≥72 ชั่วโมง** Maturation · **10+ ปี** Natural Oil & Essential Oil Expertise

> Thoughtful Care สำหรับเรา เริ่มตั้งแต่ก่อนผลิตภัณฑ์จะมาถึงมือคุณ

---

### 14 — WHAT WE CHOOSE NOT TO ADD *(เดิม Section 15)*
# สิ่งที่เราเลือกไม่ใส่ ก็เป็นส่วนหนึ่งของการคิดสูตร

**SULFATE-FREE · NO SYNTHETIC FRAGRANCE · NO ADDED SYNTHETIC COLOR · NO PRESERVATIVES**

Lavender character มาจาก Lavandula Angustifolia Oil และ Lavandula Hybrida Oil ตามสูตรจดแจ้ง

> From nature, without needing to make it louder than it is.

**หมายเหตุ:** ไม่ใช้คำว่า "Fragrance-Free" เพราะ Lavender SKU มี essential oils ที่ให้กลิ่นจริง

---

### 15 — THE RITUAL *(เดิม Section 16)*
Full-width lifestyle image

# ช่วงเวลาอาบน้ำธรรมดา ที่ให้ผิวได้รับมากกว่าความสะอาด

**REAL SOAP** สำหรับการทำความสะอาด · **MOISTURE CARE** ที่คิดถึงสิ่งที่เกิดขึ้นหลังล้าง

> ชุ่มชื้นเพิ่มขึ้น · สูญเสียน้ำน้อยลง · เป็นมิตรต่อเกราะป้องกันผิว

**Visual:** ภาพ lifestyle เต็มความกว้าง ห้องน้ำ/มุมพักผ่อนในบ้านจริง แสงธรรมชาติ (ดู Assets Available — ยังขาดภาพนี้)

---

### 16 — EVIDENCE, NOT JUST PROMISES *(เดิม Section 17)*
# สิ่งที่เราบอกว่าดี ควรมีสิ่งที่ให้คุณตรวจสอบได้

5 การ์ดหลักฐาน:
1. **REAL SOAP DEFINITION** — มอก. เอส 14-2562 นิยาม + Chemical Criteria (เกณฑ์อ้างอิงภายใน ไม่ใช่การรับรอง) — `[ ดูนิยามที่เราอ้างอิง ]`
2. **FINISHED-SOAP QUALITY‡** — Free Alkali Not Detected — `[ ดูผลการทดสอบ ]`
3. **FINISHED-SOAP ANALYSIS** — Squalene·Beta-carotene (Lavender, ม.รังสิต) / Glycerine·Alpha-tocopherol‡ (วว./TISTR) — `[ ดูผลวิเคราะห์ ]`
4. **HUMAN EFFICACY TEST** — DermX, คณะเภสัชศาสตร์ ม.เชียงใหม่, Corneometer®+Tewameter®, 20 คน, สูงสุด 8 ชม. — `[ ดูรายงาน DermX ]`
5. **THAI FDA NOTIFICATION** — เลขที่ใบรับแจ้ง 10-1-6800022354

**Visual:** พื้น Olive Grove, การ์ด 5 ใบ grid หรือ carousel บนมือถือ

---

### 17 — WHY TRUST AMOURIQ *(เดิม Section 18)*
# ความใส่ใจที่มีความรู้รองรับ

AMOURIQ เติบโตจากองค์ความรู้ด้าน Natural Oils และ Essential Oils ทีมเบื้องหลังเชื่อมมุมมองจากภูมิปัญญาแพทย์แผนไทยและเภสัชศาสตร์เครื่องสำอางสมัยใหม่

> สิ่งเหล่านั้นบอกว่าความใส่ใจของเรามาจากไหน ส่วนคุณภาพของผลิตภัณฑ์ควรให้ผลทดสอบพูดแทน

---

### 18 — CUSTOMER VOICE *(เดิม Section 19)*
# มาตรฐานบอกว่าเราเป็นอะไร เครื่องมือบอกว่าเกิดอะไรกับผิว คนใช้จริงบอกว่ารู้สึกอย่างไร

**เนื้อสัมผัสและกลิ่น — Lavender**
★★★★★ เนื้อสัมผัส: ดี · ประสิทธิภาพ: ดี · กลิ่น: หอมอ่อน
— รีวิวลูกค้าจริง Shopee, 30 mL Lavender, พฤษภาคม 2024

**ผิวหลังล้าง**
> "ชอบมากค่ะ อาบละผิวไม่แห้งเอี้ยด ปกติเป็นคนผิวแห้งมาก ใช้แล้วชอบเลยค่ะ กลิ่นก็ดี"
— รีวิวลูกค้าจริง Shopee, 30 mL Rosemary (สูตรเนื้อสบู่พื้นฐานเดียวกับ Lavender), เมษายน 2026

**เหตุผลที่ซื้อซ้ำ**
> "ได้ทดลองใช้จากขวดขนาดทดลองที่ทางร้านแถมมาให้ รู้สึกประทับใจในสัมผัส ความหอม อาบสะอาด สดชื่น เลยสั่งขวดใหญ่มาใช้เลย ทางร้านบริการดีมากๆ จัดส่งรวดเร็ว"
— รีวิวลูกค้าจริง Shopee, Rose geranium / Rosemary / Lavender, สิงหาคม 2024

**★ 4.9 จากรีวิวจริงบนร้าน Shopee** *(กรุณาตรวจสอบตัวเลขล่าสุดก่อนเผยแพร่จริง — คะแนน/จำนวนรีวิวเปลี่ยนทุกวัน)*

**Visual:** การ์ดรีวิว 3 ใบ พื้น Milk Oat ดาว 5 ดวงสี Sunbaked Clay

---

### 19 — PRODUCT BUYING BLOCK *(เดิม Section 20 — เติมราคา/ขนาดจริงแล้ว)*
**AMOURIQ**
# Organic Olive Castile Soap — Lavender

**100% REAL SOAP†**

**MOISTURE ↑** ผิวชุ่มชื้นเพิ่มขึ้น · **WATER LOSS ↓** การสูญเสียน้ำลดลง · **BARRIER-FRIENDLY** เป็นมิตรต่อเกราะป้องกันผิว

**Lavender + Lavandin Essential Oils**

---

**ขนาด & ราคา** *(ราคาเท่ากันทุกกลิ่นในไซส์เดียวกัน — Lavender / Rosemary / Rose Geranium)*

| ขนาด | ราคา | SKU (Lavender) |
|---|---:|---|
| 100 ML | ฿459 | SL-CT-OR-LV0100-1 |
| 250 ML | ฿779 | SL-CT-OR-LV0150-1 ⚠️*ยืนยัน SKU ก่อนขึ้นเว็บ* |
| 500 ML | ฿1,199 | SL-CT-OR-LV0500-1 |

**จำนวน** `− 1 +`

# **[ เพิ่มลงตะกร้า ]** (ปุ่ม Sunbaked Clay เต็มความกว้างบนมือถือ)

`DermX Tested · Thai FDA Notified · Evidence Available`

Secondary links: `ดูส่วนประกอบทั้งหมด` · `ดูผลการทดสอบ` · `ข้อมูลการจัดส่ง`

**Visual/UX:**
- ปุ่มเลือกขนาด 3 ปุ่ม (100/250/500 ML) — คลิกแล้วราคาเปลี่ยนทันที ไม่ต้องโหลดหน้าใหม่
- Component นี้ใช้ซ้ำได้กับหน้ากลิ่น Rosemary และ Rose Geranium — ราคาเหมือนกันทุกกลิ่น เปลี่ยนแค่ SKU code
- **Mobile:** Sticky Add-to-Cart bar หลัง scroll พ้น Hero — แสดงชื่อสินค้าย่อ + ราคาปัจจุบัน + ปุ่ม

---

### 20 — HOW TO USE *(เดิม Section 21)*
# เรียบง่ายสำหรับทุกวัน
**1 — WET** ทำให้ผิวเปียกด้วยน้ำ · **2 — CLEANSE** ใช้ผลิตภัณฑ์ในปริมาณพอเหมาะ ทำความสะอาดผิว · **3 — RINSE** ล้างออกด้วยน้ำสะอาด

> วิธีใช้ final ควรตรวจให้ตรงฉลากที่ได้รับอนุมัติก่อนนำขึ้นเว็บไซต์จริง

---

### 21 — FAQ *(เดิม Section 22)*

**"100% Real Soap" หมายความว่าอย่างไร?**
มอก. เอส 14-2562 นิยาม "สบู่เหลวแท้" ว่ามีเกลือของกรดไขมันจากน้ำมันหรือไขมันเป็นองค์ประกอบสำคัญ แยกจาก "สบู่เหลวผสม" ที่มีสารลดแรงตึงผิวสังเคราะห์ร่วมด้วย AMOURIQ อ้างอิงนิยามและเกณฑ์เคมีนี้มาใช้ตรวจสอบคุณภาพของเราเอง ไม่ได้หมายความว่าผ่านการขึ้นทะเบียนหรือได้รับการรับรองจากหน่วยงานผู้ออกมาตรฐาน

**ในสูตรมี Potassium Hydroxide แล้วมีด่างตกค้างหรือไม่?**
KOH เป็นส่วนหนึ่งของกระบวนการผลิต แต่คนละประเด็นกับด่างอิสระที่ตรวจพบในผลิตภัณฑ์สำเร็จรูป ผลทดสอบของ AMOURIQ ระบุ Free Alkali: Not Detect (เกณฑ์ ≤0.05%)

**ความชุ่มชื้นเพิ่มขึ้นจริงหรือไม่?**
เพิ่มขึ้น 8.48%, 13.37% และ 9.27% หลังใช้ 1, 4 และ 8 ชั่วโมงตามลำดับ มีนัยสำคัญทางสถิติ 95%

**"การสูญเสียน้ำลดลง" หมายความว่าอย่างไร?**
ค่า TEWL ต่ำกว่าก่อนใช้ 5.34%, 3.60% และ 10.67% ตามลำดับ — แต่ความแตกต่างไม่ถึงนัยสำคัญทางสถิติในการศึกษานี้

**ทำไมจึงสื่อว่า Barrier-Friendly?**
เพราะ TEWL หลังใช้ไม่ต่างจากก่อนใช้อย่างมีนัยสำคัญ และรายงานสรุปว่าไม่พบผลทำลายเกราะป้องกันผิว

**Squalene, Beta-carotene, Glycerine, Alpha-tocopherol คือส่วนผสมที่เติมเข้าไปหรือไม่?**
สื่อเฉพาะสิ่งที่มีหลักฐานตรง — "ตรวจพบ" ในผลิตภัณฑ์สำเร็จรูป ไม่อนุมานแหล่งกำเนิด/สรรพคุณเกินรายงาน

**Lavender มีกลิ่นจากอะไร?**
Lavandula Angustifolia Oil และ Lavandula Hybrida Oil ตามสูตรจดแจ้ง — ไม่ใช่ "Fragrance-Free"

**Organic ในชื่อผลิตภัณฑ์หมายถึงอะไร?**
เอกสารจดแจ้งแนบ Organic Certificate ประกอบการใช้คำว่า "ORGANIC" ในชื่อผลิตภัณฑ์

**เหมาะกับผิวแพ้ง่ายหรือไม่?**
ไม่ควรสรุปว่าเหมาะสำหรับ "Sensitive Skin" โดยเฉพาะ — การศึกษา DermX ใช้อาสาสมัครสุขภาพดีและคัดผู้มีประวัติแพ้/โรคผิวหนังออกแล้ว

**ราคาแพงกว่าสบู่เหลวทั่วไปหรือไม่?**
AMOURIQ วางตำแหน่งราคาแบบเข้าถึงได้ (Masstige) — ของแท้มาตรฐานสากลมีผลทดสอบรองรับ โดยไม่ต้องจ่ายระดับหรูหรา ต่างจากสบู่เหลวทั่วไปตรงกระบวนการที่พิถีพิถัน (พัฒนาสูตรเกือบ 3 ปี บ่ม ≥72 ชม.) และมีผลทดสอบยืนยันทุกขั้นตอน

---

### 22 — FINAL CLOSE *(เดิม Section 23)*
Full-width calm bathroom image

# เพราะการล้างผิวที่ดี ไม่ควรจบแค่คำว่า "สะอาด"

**100% REAL SOAP†** ที่ตั้งใจพัฒนาขึ้นเพื่อผิว
**MOISTURE ↑** ผิวชุ่มชื้นเพิ่มขึ้น · **WATER LOSS ↓** การสูญเสียน้ำลดลง · **BARRIER-FRIENDLY** เป็นมิตรต่อเกราะป้องกันผิว

**FREE ALKALI‡ NOT DETECTED** และการตรวจผลิตภัณฑ์สำเร็จรูปที่พบ **Squalene · Beta-carotene · Glycerine‡ · Alpha-tocopherol‡**

## AMOURIQ Organic Olive Castile Soap — Lavender
### [ เลือกการดูแลที่คิดถึงผิวหลังล้าง ]

**AMOURIQ Thoughtful Botanical Care**
**อมอริค ใส่ใจ ด้วยธรรมชาติที่คัดสรร**

---

## Internal Note *(ไม่แสดงบน Sales Page)*

สถานะ 5+2 จุดที่ต้องปิดก่อนอนุมัติทำหน้าเว็บจริง:
1. ✅ 100% REAL SOAP — wording ปรับเป็น "หยิบนิยามมาใช้" แล้ว
2. ✅ Free Alkali (มีเงื่อนไข) — ยืนยันสูตรเบสร่วมแล้ว รอ sign-off เป็นลายลักษณ์อักษรจาก regulatory/แล็บ
3. ⚠️ Squalene/Beta-carotene/Glycerine/Alpha-tocopherol — wording "Detected" ยังไม่เปิดเผยตัวเลขจริง (ปลอดภัยไว้ก่อน) รอ regulatory ยืนยันว่าใช้คำอื่นได้มากกว่านี้หรือไม่
4. ✅ TEWL — ใช้ 10.67% ตรงกับ Final Report แล้ว
5. ✅ ราคา/ขนาด — เติมจริงแล้ว (100/250/500 ML)
6. ✅ รีวิว — เติมจากข้อมูลจริงใน Shopee แล้ว
7. ⚠️ **ใหม่:** ยืนยัน SKU code 250 ML ของ Lavender (`LV0150-1` vs pattern `LV0250-1`) กับทีมก่อนขึ้นเว็บ
8. ⚠️ shipping info ยังไม่มีข้อมูลยืนยันในไฟล์ — คงเป็น placeholder
