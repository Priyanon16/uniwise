# MBS UniWise External Retriever — Portable systemd setup

ไฟล์ชุดนี้ทำให้ย้าย External Retriever ไป VPS/Cloud เครื่องใหม่ได้ง่ายขึ้น
โดยไม่ต้องเขียน `/etc/systemd/system/mbs-retriever.service` ด้วยมือทุกครั้ง

## ไฟล์

- `mbs-retriever.service.template` — template ของ systemd service
- `install_systemd.sh` — สร้าง service โดยอัตโนมัติจาก path ปัจจุบัน

## วิธีใช้บนเครื่องใหม่

วาง 2 ไฟล์นี้ไว้ในโฟลเดอร์เดียวกับ `retriever_service.py`

ตัวอย่าง:

```text
external_retriever/
├─ retriever_service.py
├─ requirements_external_retriever.txt
├─ .env
├─ .venv/
├─ data/
│  └─ dify_segments.csv
├─ mbs-retriever.service.template
└─ install_systemd.sh
```

จากนั้น:

```bash
cd /path/to/external_retriever
chmod +x install_systemd.sh
sudo ./install_systemd.sh
```

สคริปต์จะ:

1. ตรวจว่ามี `.env`, `.venv/bin/uvicorn`, `retriever_service.py`
2. ตรวจ path ปัจจุบันอัตโนมัติ
3. สร้าง `/etc/systemd/system/mbs-retriever.service`
4. รัน `systemctl daemon-reload`
5. `enable --now` ให้ service เปิดเองหลัง reboot
6. แสดง status
7. ทดสอบ `http://127.0.0.1:8000/health`

## คำสั่งดูสถานะ

```bash
systemctl status mbs-retriever --no-pager
journalctl -u mbs-retriever -n 100 --no-pager
curl http://127.0.0.1:8000/health
```

## สำคัญ

- ห้าม commit `.env`
- ห้าม commit API keys
- ถ้าย้ายเครื่อง ให้สร้าง/คัดลอก `.env` อย่างปลอดภัย
- ต้องติดตั้ง Python dependencies และสร้าง `.venv` ก่อนรัน install script
