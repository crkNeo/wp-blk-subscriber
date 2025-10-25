# Venus Member System - WordPress Plugin

## 插件概述
Venus 會員系統是一個 WordPress 插件，整合 WooCommerce my-account 頁面，提供完整的會員申請流程。

## 功能流程

### 1. Footer 申請按鈕
- **位置**: 網站 footer 區域
- **功能**: 「申請成為Venus會員」按鈕
- **邏輯**:
  - 未登入用戶 → 跳轉到 WooCommerce 註冊頁面 (`/my-account/`)
  - 已登入用戶 → 跳轉到會員權益書頁面 (`/consent-form/`)

### 2. 會員權益書頁面 (consent-form)
- **路徑**: `/consent-form/`
- **功能**: 顯示會員權益、條款說明
- **內容**:
  - 會員專屬權益介紹
  - 會員義務說明
  - 費用說明 (年費 NT$1,000)
  - 注意事項
- **操作**: 用戶勾選同意後，點擊「同意並開始申請」

### 3. 數位簽名頁面 (點點簽)
- **狀態**: 🚧 **暫不實作，先保留介面**
- **功能**: 整合點點簽 API 進行數位簽名
- **暫時處理**: 顯示簽名區域，點擊「完成簽名」直接進入下一步

### 4. 金流付款頁面
- **狀態**: 🚧 **暫不實作，先保留介面**
- **功能**: 整合金流 API 處理付款
- **暫時處理**: 顯示付款選項，點擊「確認付款」直接模擬付款成功

### 5. 會員頁面
- **路徑**: `/my-account/`
- **功能**: 付款完成後的會員專屬頁面
- **內容**:
  - 會員資訊顯示
  - 申請狀態追蹤
  - 身分驗證入口

### 6. 身分驗證 (文件上傳)
- **路徑**: `/my-account/venus-documents/`
- **必填文件**:
  - 身分證正面
  - 身分證反面
- **選填文件**:
  - 銀行存摺 (在會員中心提供選填功能)
- **功能**: 文件上傳、預覽、管理

## 技術架構

### 插件結構
```
venus-member-plugin/
├── venus-member-plugin.php          # 主插件文件
├── includes/
│   ├── class-venus-database.php     # 數據庫處理類
│   ├── class-venus-application.php  # 申請流程處理類
│   ├── class-venus-documents.php    # 文件上傳處理類
│   └── class-venus-payment.php      # 付款處理類 (暫不實作)
├── templates/
│   ├── consent-form.php             # 會員權益書模板
│   ├── signature-form.php           # 簽名頁面模板 (暫不實作)
│   ├── payment-form.php             # 付款頁面模板 (暫不實作)
│   ├── member-dashboard.php         # 會員頁面模板
│   └── document-upload.php          # 文件上傳模板
├── assets/
│   ├── css/
│   │   └── venus-member.css
│   └── js/
│       └── venus-member.js
└── README.md
```

### WooCommerce 整合
- **端點註冊**:
  - `venus-documents` - 身分驗證
- **WordPress 頁面**:
  - `/consent-form/` - 會員權益書 (使用 [venus_consent_form] 短代碼)
- **選單整合**: 在 my-account 側邊選單添加相關項目

### 數據庫設計
- `wp_venus_member_applications` - 會員申請記錄
- `wp_venus_application_documents` - 申請文件記錄
- `wp_venus_consent_records` - 同意書記錄
- `wp_venus_payment_records` - 付款記錄 (暫不使用)
- `wp_venus_audit_logs` - 審核日誌

## 實作階段

### Phase 1: 基礎流程 ✅
- [x] 插件基礎架構
- [x] 數據庫設計
- [x] Footer 申請按鈕
- [x] 會員權益書頁面 (consent-form)
- [x] WooCommerce my-account 整合

### Phase 2: 核心功能 ✅
- [x] 文件上傳功能 (venus-documents)
- [x] 獨立 consent-form 頁面 (短代碼實現)
- [x] 登入檢查邏輯
- [ ] 簽名頁面介面 (不實作 API)
- [ ] 付款頁面介面 (不實作 API)

### Phase 3: 進階功能 ⏳
- [ ] 管理後台
- [ ] 審核流程

### Phase 4: 第三方整合 ⏳
- [ ] 點點簽 API 整合
- [ ] 金流 API 整合

## 頁面流程圖

```
Footer 按鈕
    ↓
未登入? → 註冊頁面 (/my-account/)
    ↓
已登入? → 會員權益書 (/consent-form/)
    ↓
同意條款 → 數位簽名頁面 (暫不實作 API)
    ↓
完成簽名 → 金流付款頁面 (暫不實作 API)
    ↓
付款完成 → 會員頁面 (/my-account/venus-member/)
    ↓
身分驗證 → 文件上傳 (/my-account/venus-documents/)
    ↓
審核完成 → 正式開通會員
```

## 開發注意事項

1. **相容性**: 確保與 WooCommerce 和 WordPress 最新版本相容
2. **安全性**: 文件上傳需要嚴格的安全檢查
3. **響應式**: 所有頁面需支援手機版
4. **多語言**: 預留國際化支援
5. **效能**: 優化數據庫查詢和文件處理

## 設定說明

### 必要插件
- WordPress 5.0+
- WooCommerce 5.0+

### 安裝步驟
1. 上傳插件到 `/wp-content/plugins/` 目錄
2. 在 WordPress 管理後台啟用插件
3. 插件會自動創建必要的數據庫表
4. 在 WooCommerce → 帳戶 中查看新增的端點

---

**確認事項**:
1. 流程是否符合需求？
2. 頁面命名 (consent-form) 是否正確？
3. 暫不實作的功能 (點點簽、金流) 處理方式是否合適？
4. 還有其他需要調整的地方嗎？