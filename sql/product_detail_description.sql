-- SQL IMPORT-READY CHO XAMPP/phpMyAdmin
-- Mục tiêu: tạo dữ liệu mô tả chi tiết từng sản phẩm và view dùng trực tiếp cho drink-detail.php

CREATE DATABASE IF NOT EXISTS `qlhethongbanhangmini`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `qlhethongbanhangmini`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Tạo bảng mô tả chi tiết (nếu chưa có)
CREATE TABLE IF NOT EXISTS `hanghoa_mota_chitiet` (
  `MaHang` varchar(10) NOT NULL,
  `MoTaChiTiet` text NOT NULL,
  `NgayCapNhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`MaHang`),
  CONSTRAINT `fk_hanghoa_mota_chitiet_mahang`
    FOREIGN KEY (`MaHang`) REFERENCES `hanghoa` (`MaHang`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Seed/cập nhật mô tả chi tiết cho toàn bộ sản phẩm (mỗi mã hàng 1 mô tả riêng)
INSERT INTO `hanghoa_mota_chitiet` (`MaHang`, `MoTaChiTiet`)
SELECT
    h.MaHang,
    CONCAT(
      'Tên sản phẩm: ', h.TenHang, '. ',
      'Mã tham chiếu: ', h.MaHang, '. ',
      'Phân loại: ', COALESCE(nh.TenNhomHang, 'Chưa phân loại'), '. ',
      'Quy cách: ', COALESCE(NULLIF(h.DVT, ''), 'Theo tiêu chuẩn nhà sản xuất'), '. ',
      'Thành phần chính: ',
      CASE
        WHEN h.MaNhomHang IN ('NH01', 'NH07') THEN 'nền nước tinh khiết, chất tạo vị/hương theo công bố trên nhãn sản phẩm.'
        WHEN h.MaNhomHang = 'NH02' THEN 'tinh bột ngũ cốc/khoai, dầu thực vật và gia vị theo công thức sản phẩm.'
        WHEN h.MaNhomHang = 'NH03' THEN 'bột mì, đường, chất béo thực phẩm và thành phần phụ trợ theo công bố.'
        WHEN h.MaNhomHang = 'NH04' THEN 'nguyên liệu trái cây tự nhiên theo mùa vụ.'
        WHEN h.MaNhomHang = 'NH05' THEN 'sữa và các vi chất bổ sung theo tiêu chuẩn nhà sản xuất.'
        WHEN h.MaNhomHang = 'NH06' THEN 'sợi mì từ bột mì/tinh bột và gói gia vị đi kèm.'
        WHEN h.MaNhomHang = 'NH08' THEN 'vật liệu/cấu tạo gia dụng theo thông số kỹ thuật của nhà sản xuất.'
        WHEN h.MaNhomHang = 'NH09' THEN 'rau củ tươi tự nhiên, sơ chế theo quy chuẩn an toàn thực phẩm.'
        WHEN h.MaNhomHang = 'NH10' THEN 'thực phẩm tươi sống tự nhiên theo từng mặt hàng.'
        WHEN h.MaNhomHang = 'NH11' THEN 'sữa, đường, hương vị thực phẩm và phụ liệu theo công bố sản phẩm.'
        WHEN h.MaNhomHang = 'NH14' THEN 'thành phần phối trộn từ tinh bột, đạm và gia vị theo từng món.'
        WHEN h.MaNhomHang = 'NH15' THEN 'hoạt chất và tá dược mỹ phẩm theo công bố thành phần.'
        WHEN h.MaNhomHang = 'NH16' THEN 'nước, malt/men hoặc nguyên liệu lên men theo dòng đồ uống có cồn.'
        ELSE 'theo công bố thành phần của nhà sản xuất trên bao bì.'
      END,
      ' ',
      'Chất liệu/bao bì: ',
      CASE
        WHEN h.MaNhomHang IN ('NH01','NH07','NH16') THEN 'chai/lon đạt chuẩn tiếp xúc thực phẩm, niêm phong trước khi phân phối.'
        WHEN h.MaNhomHang IN ('NH02','NH03','NH06','NH11','NH14') THEN 'bao bì màng ghép hoặc hộp thực phẩm, đảm bảo vệ sinh và kín ẩm.'
        WHEN h.MaNhomHang IN ('NH04','NH09','NH10') THEN 'khay/túi/hộp thực phẩm chuyên dụng cho hàng tươi.'
        WHEN h.MaNhomHang = 'NH15' THEN 'chai/tuýp/lọ mỹ phẩm chuyên dụng, hạn chế nhiễm bẩn sau khi mở nắp.'
        ELSE 'đóng gói bằng vật liệu đạt chuẩn an toàn theo quy định hiện hành.'
      END,
      ' ',
      'Công dụng nổi bật: ',
      CASE
        WHEN h.MaNhomHang IN ('NH01','NH07') THEN CONCAT('hỗ trợ giải khát nhanh, tiện dùng hằng ngày với sản phẩm ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH02' THEN CONCAT('ăn nhẹ tiện lợi, phù hợp bữa phụ với sản phẩm ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH03' THEN CONCAT('dùng cho bữa phụ và tráng miệng với sản phẩm ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH04' THEN CONCAT('bổ sung vitamin và chất xơ tự nhiên từ ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH05' THEN CONCAT('hỗ trợ bổ sung dinh dưỡng hằng ngày với ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH06' THEN CONCAT('chuẩn bị bữa ăn nhanh gọn, tiết kiệm thời gian với ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH08' THEN CONCAT('hỗ trợ sinh hoạt gia đình hiệu quả hơn bằng ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH09' THEN CONCAT('cung cấp dinh dưỡng từ rau củ tươi qua ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH10' THEN CONCAT('phục vụ chế biến bữa ăn giàu đạm với ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH11' THEN CONCAT('món tráng miệng mát lạnh, dễ thưởng thức với ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH14' THEN CONCAT('tiện dùng cho bữa ăn nhanh cùng ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH15' THEN CONCAT('hỗ trợ chăm sóc da/cơ thể theo công năng của ', h.TenHang, '.')
        WHEN h.MaNhomHang = 'NH16' THEN CONCAT('phù hợp các dịp gặp gỡ khi sử dụng có trách nhiệm với ', h.TenHang, '.')
        ELSE CONCAT('đáp ứng nhu cầu sử dụng hằng ngày với sản phẩm ', h.TenHang, '.')
      END,
      ' ',
      'Xuất xứ tham khảo: Việt Nam. ',
      'Hướng dẫn sử dụng: sử dụng đúng mục đích sản phẩm, đọc kỹ nhãn trước khi dùng. ',
      'Bảo quản: để nơi khô ráo, thoáng mát, tránh ánh nắng trực tiếp và nhiệt độ cao. ',
      'Lưu ý: kiểm tra tình trạng bao bì và hạn sử dụng trước khi sử dụng.'
    ) AS MoTaChiTiet
FROM hanghoa h
LEFT JOIN nhomhang nh ON nh.MaNhomHang = h.MaNhomHang
ON DUPLICATE KEY UPDATE
    `MoTaChiTiet` = VALUES(`MoTaChiTiet`),
    `NgayCapNhat` = CURRENT_TIMESTAMP;

-- 3) Tạo lại view dùng cho trang drink-detail.php
DROP VIEW IF EXISTS `vw_product_detail_page`;

CREATE VIEW `vw_product_detail_page` AS
SELECT
    h.MaHang AS id,
    h.TenHang AS name,
    h.DVT AS unit,
    h.DonGia AS price_before_tax,
    h.SoTienCoThue AS price,
    h.VAT AS vat,
    h.HinhAnh AS image,
    nh.MaNhomHang AS group_code,
    nh.TenNhomHang AS group_name,
    COALESCE(m.MoTaChiTiet, '') AS detail_description
FROM hanghoa h
LEFT JOIN nhomhang nh ON nh.MaNhomHang = h.MaNhomHang
LEFT JOIN hanghoa_mota_chitiet m ON m.MaHang = h.MaHang;

SET FOREIGN_KEY_CHECKS = 1;

-- ====== TEST NHANH SAU KHI IMPORT ======
-- SELECT * FROM vw_product_detail_page WHERE id = 'HH061' LIMIT 1;
-- SELECT MaHang, TenHang FROM hanghoa ORDER BY MaHang;
