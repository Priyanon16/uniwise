# install_apache_proxy.sh

สคริปต์สำหรับติดตั้ง Apache Reverse Proxy ของ MBS UniWise External Retriever
บน Debian/Ubuntu โดยอัตโนมัติ

ค่าเริ่มต้น:

- Public path: `/mbs-retriever/`
- Backend: `http://127.0.0.1:8000/`

## ใช้บนเครื่องใหม่

วางไฟล์นี้ไว้ใน `external_retriever` แล้วรัน:

```bash
chmod +x install_apache_proxy.sh
sudo ./install_apache_proxy.sh
```

จากนั้นทดสอบ:

```text
http://SERVER-IP/mbs-retriever/health
```

ควรได้ `"status":"ok"`.

## หากต้องการเปลี่ยน path หรือ port

```bash
PUBLIC_PATH=/retriever/ \
BACKEND_URL=http://127.0.0.1:9000/ \
sudo ./install_apache_proxy.sh
```

สคริปต์จะ:
1. ตรวจ Apache
2. เปิด modules `proxy`, `proxy_http`, `headers`
3. backup config เดิมถ้ามี
4. สร้าง `/etc/apache2/conf-available/mbs-retriever.conf`
5. enable config
6. รัน `apache2ctl configtest`
7. reload Apache
8. ทดสอบ local `/health` แบบ best-effort
