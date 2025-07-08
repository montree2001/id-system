/**
 * ระบบอ่านบัตรประชาชนสำหรับเครื่องอ่าน Zoweetek ZW-12026-3
 * รองรับทั้ง ActiveX Control และ WebUSB API
 */

class ThaiIDCardReader {
    constructor() {
        this.isConnected = false;
        this.isReading = false;
        this.readerType = null; // 'activex', 'webusb', หรือ 'simulation'
        this.device = null;
        this.simulationMode = true; // เปลี่ยนเป็น false เมื่อใช้งานจริง
        
        // ข้อมูลจำลองสำหรับทดสอบ
        this.simulationData = {
            citizenId: '1234567890123',
            titleTh: 'นาย',
            firstnameTh: 'สมชาย',
            lastnameTh: 'ใจดี',
            titleEn: 'Mr.',
            firstnameEn: 'Somchai',
            lastnameEn: 'Jaidee',
            birthDate: '1990-05-15',
            gender: 'M',
            address: '123 หมู่ 1 ตำบลบางเขน',
            district: 'บางเขน',
            amphoe: 'เมือง',
            province: 'นนทบุรี',
            postalCode: '11000',
            issueDate: '2020-05-15',
            expireDate: '2030-05-14',
            issuer: 'สำนักงานเขตบางเขน',
            photo: null // จะใส่ base64 ของรูปภาพ
        };
    }

    /**
     * เริ่มต้นเครื่องอ่านบัตร
     */
    async init() {
        console.log('Initializing card reader...');
        
        if (this.simulationMode) {
            this.readerType = 'simulation';
            this.isConnected = true;
            this.updateConnectionStatus();
            return true;
        }

        // ลองเชื่อมต่อผ่าน WebUSB ก่อน
        if (await this.initWebUSB()) {
            this.readerType = 'webusb';
            return true;
        }

        // ถ้าไม่ได้ให้ลอง ActiveX
        if (await this.initActiveX()) {
            this.readerType = 'activex';
            return true;
        }

        console.error('Unable to initialize card reader');
        return false;
    }

    /**
     * เริ่มต้นผ่าน WebUSB API
     */
    async initWebUSB() {
        try {
            if (!navigator.usb) {
                console.log('WebUSB not supported');
                return false;
            }

            // กำหนด Vendor ID และ Product ID สำหรับ Zoweetek ZW-12026-3
            // ต้องหา VID/PID จริงจากผู้ผลิต
            const filters = [
                { vendorId: 0x072F, productId: 0x90CC }, // ตัวอย่าง VID/PID
                { vendorId: 0x072F, productId: 0x90DE }  // อาจมีหลายรุ่น
            ];

            const devices = await navigator.usb.getDevices();
            this.device = devices.find(device => 
                filters.some(filter => 
                    device.vendorId === filter.vendorId && 
                    device.productId === filter.productId
                )
            );

            if (!this.device) {
                // ลองขอสิทธิ์เข้าถึงอุปกรณ์ใหม่
                this.device = await navigator.usb.requestDevice({ filters });
            }

            if (this.device) {
                await this.device.open();
                await this.device.selectConfiguration(1);
                await this.device.claimInterface(0);
                
                this.isConnected = true;
                this.updateConnectionStatus();
                console.log('WebUSB card reader connected');
                return true;
            }
        } catch (error) {
            console.error('WebUSB initialization failed:', error);
        }
        return false;
    }

    /**
     * เริ่มต้นผ่าน ActiveX Control
     */
    async initActiveX() {
        try {
            // สำหรับ Internet Explorer และ Edge Legacy
            if (typeof ActiveXObject !== 'undefined') {
                // ใช้ CLSID ของ Zoweetek driver
                // ต้องได้ CLSID จริงจากผู้ผลิต
                this.device = new ActiveXObject("ZoweetekCardReader.Application");
                
                if (this.device && this.device.Initialize()) {
                    this.isConnected = true;
                    this.updateConnectionStatus();
                    console.log('ActiveX card reader connected');
                    return true;
                }
            }
        } catch (error) {
            console.error('ActiveX initialization failed:', error);
        }
        return false;
    }

    /**
     * อ่านข้อมูลจากบัตรประชาชน
     */
    async readCard() {
        if (!this.isConnected || this.isReading) {
            return null;
        }

        this.isReading = true;
        this.updateReadingStatus();

        try {
            let cardData = null;

            switch (this.readerType) {
                case 'simulation':
                    cardData = await this.readCardSimulation();
                    break;
                case 'webusb':
                    cardData = await this.readCardWebUSB();
                    break;
                case 'activex':
                    cardData = await this.readCardActiveX();
                    break;
            }

            if (cardData) {
                console.log('Card data read successfully:', cardData);
                this.displayCardData(cardData);
                currentCardData = cardData;
            }

            return cardData;

        } catch (error) {
            console.error('Error reading card:', error);
            showAlert('ข้อผิดพลาด', 'ไม่สามารถอ่านข้อมูลจากบัตรได้: ' + error.message);
            return null;
        } finally {
            this.isReading = false;
            this.updateReadingStatus();
        }
    }

    /**
     * อ่านข้อมูลแบบจำลอง (สำหรับทดสอบ)
     */
    async readCardSimulation() {
        // จำลองเวลาในการอ่านบัตร
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // สุ่มข้อมูลใหม่บ้าง
        const names = [
            { th: 'สมชาย ใจดี', en: 'Somchai Jaidee' },
            { th: 'สมหญิง รักษ์ดี', en: 'Somying Rakdee' },
            { th: 'สมศักดิ์ พร้อมใจ', en: 'Somsak Promjai' },
            { th: 'สุดา มั่นใจ', en: 'Suda Manjai' }
        ];
        
        const randomName = names[Math.floor(Math.random() * names.length)];
        const randomId = Math.floor(Math.random() * 9000000000000) + 1000000000000;
        
        return {
            ...this.simulationData,
            citizenId: randomId.toString(),
            firstnameTh: randomName.th.split(' ')[0],
            lastnameTh: randomName.th.split(' ')[1],
            firstnameEn: randomName.en.split(' ')[0],
            lastnameEn: randomName.en.split(' ')[1],
            photo: await this.generateSamplePhoto()
        };
    }

    /**
     * อ่านข้อมูลผ่าน WebUSB
     */
    async readCardWebUSB() {
        if (!this.device) throw new Error('Device not connected');

        try {
            // ส่งคำสั่งอ่านบัตรไปยังเครื่องอ่าน
            // คำสั่งนี้ขึ้นอยู่กับ protocol ของ Zoweetek
            const command = new Uint8Array([0x80, 0xCA, 0x00, 0x00, 0x00]); // ตัวอย่างคำสั่ง
            
            await this.device.transferOut(1, command);
            
            // รอรับข้อมูลกลับ
            const result = await this.device.transferIn(1, 1024);
            
            if (result.status === 'ok') {
                return this.parseCardData(result.data);
            } else {
                throw new Error('Failed to read card data');
            }
        } catch (error) {
            throw new Error('WebUSB read error: ' + error.message);
        }
    }

    /**
     * อ่านข้อมูลผ่าน ActiveX
     */
    async readCardActiveX() {
        if (!this.device) throw new Error('Device not connected');

        try {
            // เรียกใช้ method ของ ActiveX control
            const result = this.device.ReadCard();
            
            if (result && result.Success) {
                return {
                    citizenId: result.CitizenID,
                    titleTh: result.TitleTH,
                    firstnameTh: result.FirstNameTH,
                    lastnameTh: result.LastNameTH,
                    titleEn: result.TitleEN,
                    firstnameEn: result.FirstNameEN,
                    lastnameEn: result.LastNameEN,
                    birthDate: result.BirthDate,
                    gender: result.Gender,
                    address: result.Address,
                    district: result.District,
                    amphoe: result.Amphoe,
                    province: result.Province,
                    postalCode: result.PostalCode,
                    issueDate: result.IssueDate,
                    expireDate: result.ExpireDate,
                    issuer: result.Issuer,
                    photo: result.Photo
                };
            } else {
                throw new Error('Failed to read card data');
            }
        } catch (error) {
            throw new Error('ActiveX read error: ' + error.message);
        }
    }

    /**
     * แปลงข้อมูลดิบจากเครื่องอ่าน
     */
    parseCardData(rawData) {
        // ใช้งานตาม protocol ของเครื่องอ่านบัตร
        // นี่เป็นตัวอย่างการแปลงข้อมูล
        const decoder = new TextDecoder('utf-8');
        const dataString = decoder.decode(rawData);
        
        // แปลงข้อมูลตาม format ที่ได้จากเครื่อง
        // ต้องศึกษา protocol จริงของ Zoweetek
        
        return this.simulationData; // ใช้ข้อมูลจำลองไปก่อน
    }

    /**
     * สร้างรูปภาพตัวอย่าง
     */
    async generateSamplePhoto() {
        // สร้าง canvas สำหรับรูปตัวอย่าง
        const canvas = document.createElement('canvas');
        canvas.width = 120;
        canvas.height = 160;
        const ctx = canvas.getContext('2d');
        
        // วาดพื้นหลัง
        ctx.fillStyle = '#f0f0f0';
        ctx.fillRect(0, 0, 120, 160);
        
        // วาดไอคอนคน
        ctx.fillStyle = '#333';
        ctx.font = '60px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('👤', 60, 100);
        
        return canvas.toDataURL('image/jpeg', 0.8);
    }

    /**
     * แสดงข้อมูลบัตรบน UI
     */
    displayCardData(data) {
        document.getElementById('citizenId').value = data.citizenId || '';
        document.getElementById('nameTh').value = `${data.titleTh || ''}${data.firstnameTh || ''} ${data.lastnameTh || ''}`.trim();
        document.getElementById('nameEn').value = `${data.titleEn || ''}${data.firstnameEn || ''} ${data.lastnameEn || ''}`.trim();
        document.getElementById('birthDate').value = this.formatDate(data.birthDate) || '';
        document.getElementById('gender').value = data.gender === 'M' ? 'ชาย' : 'หญิง';
        
        const address = [
            data.address,
            data.district,
            data.amphoe,
            data.province,
            data.postalCode
        ].filter(item => item).join(' ');
        
        document.getElementById('address').value = address;
        
        // แสดงรูปภาพ
        if (data.photo) {
            const photoImg = document.getElementById('photoPreview');
            photoImg.src = data.photo;
            photoImg.style.display = 'block';
            document.getElementById('noPhoto').style.display = 'none';
        }
        
        document.getElementById('cardDataSection').style.display = 'block';
    }

    /**
     * จัดรูปแบบวันที่
     */
    formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('th-TH');
    }

    /**
     * อัปเดตสถานะการเชื่อมต่อ
     */
    updateConnectionStatus() {
        const statusEl = document.getElementById('connectionStatus');
        const textEl = document.getElementById('connectionText');
        const btnEl = document.getElementById('btnReadCard');

        if (this.isConnected) {
            statusEl.className = 'status-indicator status-connected';
            textEl.textContent = `เชื่อมต่อเครื่องอ่านบัตรแล้ว (${this.readerType})`;
            btnEl.disabled = false;
            document.getElementById('btnText').textContent = 'เสียบบัตรประชาชนเพื่ออ่านข้อมูล';
        } else {
            statusEl.className = 'status-indicator status-disconnected';
            textEl.textContent = 'ไม่ได้เชื่อมต่อเครื่องอ่านบัตร';
            btnEl.disabled = true;
        }
    }

    /**
     * อัปเดตสถานะการอ่านบัตร
     */
    updateReadingStatus() {
        const statusEl = document.getElementById('connectionStatus');
        const btnEl = document.getElementById('btnReadCard');
        const spinnerEl = document.querySelector('.loading-spinner');
        const btnTextEl = document.getElementById('btnText');

        if (this.isReading) {
            statusEl.className = 'status-indicator status-reading';
            btnEl.disabled = true;
            spinnerEl.style.display = 'inline-block';
            btnTextEl.textContent = 'กำลังอ่านข้อมูลจากบัตร...';
        } else {
            this.updateConnectionStatus();
            spinnerEl.style.display = 'none';
        }
    }
}

// สร้าง instance ของเครื่องอ่านบัตร
let cardReader;

/**
 * เริ่มต้นเครื่องอ่านบัตร
 */
async function initCardReader() {
    cardReader = new ThaiIDCardReader();
    const success = await cardReader.init();
    
    if (success) {
        console.log('Card reader initialized successfully');
        
        // เพิ่ม event listener สำหรับปุ่มอ่านบัตร
        document.getElementById('btnReadCard').addEventListener('click', async () => {
            await cardReader.readCard();
        });
        
        // เพิ่ม keyboard shortcut
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                if (!cardReader.isReading) {
                    cardReader.readCard();
                }
            }
        });
        
    } else {
        console.error('Failed to initialize card reader');
        showAlert('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเครื่องอ่านบัตรได้ กรุณาตรวจสอบการเชื่อมต่อ');
    }
}

/**
 * ตรวจสอบการเสียบบัตรอัตโนมัติ (สำหรับบางรุ่นที่รองรับ)
 */
function startCardDetection() {
    if (!cardReader || !cardReader.isConnected) return;
    
    // ตรวจสอบทุก 1 วินาที
    setInterval(async () => {
        if (!cardReader.isReading) {
            try {
                // ตรวจสอบว่ามีบัตรเสียบอยู่หรือไม่
                const hasCard = await cardReader.detectCard();
                if (hasCard && !currentCardData) {
                    await cardReader.readCard();
                }
            } catch (error) {
                // ไม่ต้องแสดง error สำหรับการตรวจสอบอัตโนมัติ
            }
        }
    }, 1000);
}