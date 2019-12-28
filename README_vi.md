# Xampp vHosts Manager
Hệ thống quản lý các tên miền ảo (cùng chứng chỉ SSL) cho Xampp trên nền hệ điều hành Windows

Ngôn ngữ hướng dẫn >>> ( [Tiếng Anh](https://github.com/JackieDo/Xampp-vHosts-Manager/blob/master/README.md) | Tiếng Việt )

![Xampp vHosts Manager GitHub cover](https://user-images.githubusercontent.com/9862115/70820328-f78de800-1e0a-11ea-894a-b7021942c158.jpg)

Có ai đó đã từng hỏi tôi

> ***Làm thế nào để cấu hình và quản lý các tên miền ảo cho Xampp trên hệ điều hành Windows?***

Một vài người khác lại hỏi

> ***Làm thế nào để thêm chứng chỉ SSL tự ký vào các tên miền ảo của Xampp nhanh nhất có thể và dễ dàng quản lý chúng mà không gặp rắc rối khi sử dụng các lệnh OPENSSL?***

Vì thế, dự án này đã ra đời, nhằm tăng cường sức mạnh cho Xampp, giúp người dùng tận dụng các tài nguyên vốn có của Xampp để thực hiện các mục đích trên một cách nhanh chóng và dễ dàng nhất có thể.

> _Lưu ý: Hiện tại dự án này chỉ hỗ trợ người dùng Windows._

# Những tính năng của ứng dụng
* Tạo tên miền ảo.
* Xóa tên miền ảo.
* Hiển thị thông tin của tên miền ảo.
* Liệt kê tất cả các tiên miền ảo hiện có.
* Thêm chứng chỉ SSL cho một tên miền ảo.
* Gỡ bỏ chứng chỉ SSL của tên miền ảo.
* Thay đổi Document Root của một tên miền ảo.
* Dừng dịch vụ Apache Httpd.
* Chạy dịch vụ Apache Httpd.
* Khởi động lại dịch vụ Apache Httpd.

# Tổng quan
Hãy tìm đọc một trong những chủ đề sau để tìm hiểu thêm về Xampp vHosts Manager (XVHM).

* [Sự tương thích](#sự-tương-thích)
* [Yêu cầu](#yêu-cầu)
* [Cài đặt](#cài-đặt)
    - [Thông qua Composer Create-Project](#thông-qua-composer-create-project)
    - [Dùng phương thức tải về thủ công](#dùng-phương-thức-tải-về-thủ-công)
* [Sử dụng](#sử-dụng)
    - [Hiển thị trợ giúp](#hiển-thị-trợ-giúp)
    - [Tạo tên miền ảo mới](#tạo-tên-miền-ảo-mới)
    - [Hiển thị thông tin của một tên miền ảo hiện có](#hiển-thị-thông-tin-của-một-tên-miền-ảo-hiện-có)
    - [Liệt kê tất cả các tên miền ảo hiện đang có](#liệt-kê-tất-cả-các-tên-miền-ảo-hiện-đang-có)
    - [Xóa bỏ một tên miền ảo](#xóa-bỏ-một-tên-miền-ảo)
    - [Thêm chứng chỉ SSL cho một tên miền ảo](#thêm-chứng-chỉ-ssl-cho-một-tên-miền-ảo)
    - [Gỡ bỏ chứng chỉ SSL của một tên miền ảo](#gỡ-bỏ-chứng-chỉ-ssl-của-một-tên-miền-ảo)
    - [Thay đổi Document Root của một tên miền ảo](#thay-đổi-document-root-của-một-tên-miền-ảo)
    - [Dừng Apache Httpd](#dừng-apache-httpd)
    - [Chạy Apache Httpd](#chạy-apache-httpd)
    - [Khởi động lại Apache Httpd](#khởi-động-lại-apache-httpd)
    - [Đăng ký đường dẫn của ứng dụng](#đăng-ký-đường-dẫn-của-ứng-dụng)
* [Cấu hình](#cấu-hình)
* [Giấy phép](#giấy-phép)
* [Lời cảm ơn](#lời-cảm-ơn)

## Sự tương thích
XVHM tương thích với tất cả các phiên bản Xampp sử dụng PHP 5.4 trở lên.

## Yêu cầu
XVHM tận dụng tối đa những gì có trong Xampp, không cần thêm gì nữa. Vì vậy, bạn chỉ cần những điều sau đây:

* Đã cài đặt Xampp (lẽ dĩ nhiên).
* Đã thêm đường dẫn đến thư mục PHP của Xampp vào biến môi trường đường dẫn của Windows.
* (Tùy chọn thêm) Đã cài đặt Composer.

> _Lưu ý: Xem [tại đây](https://helpdeskgeek.com/windows-10/add-windows-path-environment-variable/) để biết cách thêm đường dẫn vào biến môi trường đường dẫn của Windows._

## Cài đặt
Có hai phương pháp cài đặt, thông qua Composer hoặc tải xuống thủ công. Bạn nên sử dụng phương thức thông qua Composer nếu bạn đã cài đặt nó.

#### Thông qua Composer Create-Project
* Mở dấu nhắc lệnh.
* Điều hướng đến thư mục bạn muốn cài đặt XVHM vào.
* Chạy lệnh composer create-project:
```
$ composer create-project jackiedo/xampp-vhosts-manager xvhm "1.*"
```

#### Dùng phương thức tải về thủ công
* Tải về [phiên bản mới nhất](https://github.com/JackieDo/Xampp-vHosts-Manager/releases/latest)
* Giải nén bản cài đặt vào một nơi nào đó `(ví dụ: D:\xvhm)`. Lưu ý: Không nên đặt trong `C:\Program Files` hoặc bất cứ nơi nào đỏi hỏi quyền Administrator khi ta chỉnh sửa tập tin cấu hình về sau.
* Mở dấu nhắc lệnh trong chế độ Administrator `(run as Administrator)`.
* Điều hướng đến thư mục XVHM bạn đã giải nén `(ví dụ: cd /D D:\xvhm)`.
* Thực thi lệnh `xvhosts install` và làm theo từng bước yêu cầu.
* Đóng dấu nhắc lệnh (mục đích để xóa các biến tạm).

> Lưu ý: Xem [tại đây](https://www.howtogeek.com/194041/how-to-open-the-command-prompt-as-administrator-in-windows-8.1/) để biết cách mở dấu nhắc lệnh với quyền Administrator.

## Sử dụng
Do đường dẫn đến thư mục ứng dụng XVHM đã được thêm vào biến môi trường đường dẫn trong quá trình cài đặt, bây giờ bạn chỉ cần mở dấu nhắc lệnh `(không cần thiết mở với quyền Administrator nữa)` ở bất cứ nơi đâu và thực hiện một trong các lệnh `xvhosts` sau:

#### Hiển thị trợ giúp

Cú pháp:
```
$ xvhosts help
```

#### Tạo tên miền ảo mới

Cú pháp:
```
$ xvhosts new [HOST_NAME]
```

Ví dụ:
```
$ xvhosts new demo.local
```

> Lưu ý: Tham số HOST_NAME là tùy chọn. Nếu bạn không truyền nó vào câu lệnh lệnh, bạn cũng sẽ được yêu cầu nhập thông tin này sau đó.

#### Hiển thị thông tin của một tên miền ảo hiện có

Cú pháp:
```
$ xvhosts show [HOST_NAME]
```

Ví dụ:
```
$ xvhosts show demo.local
```

#### Liệt kê tất cả các tên miền ảo hiện đang có

Cú pháp:
```
$ xvhosts list
```

#### Xóa bỏ một tên miền ảo

Cú pháp:
```
$ xvhosts remove [HOST_NAME]
```

Ví dụ:
```
$ xvhosts remove demo.local
```

#### Thêm chứng chỉ SSL cho một tên miền ảo

Cú pháp:
```
$ xvhosts add_ssl [HOST_NAME]
```

Ví dụ:
```
$ xvhosts add_ssl demo.local
```

#### Gỡ bỏ chứng chỉ SSL của một tên miền ảo

Cú pháp:
```
$ xvhosts remove_ssl [HOST_NAME]
```

Ví dụ:
```
$ xvhosts remove_ssl demo.local
```

#### Thay đổi Document Root của một tên miền ảo

Cú pháp:
```
$ xvhosts change_docroot [HOST_NAME]
```

Ví dụ:
```
$ xvhosts change_docroot demo.local
```

#### Dừng Apache Httpd

Cú pháp:
```
$ xvhosts stop_apache
```

#### Chạy Apache Httpd

Cú pháp:
```
$ xvhosts start_apache
```

#### Khởi động lại Apache Httpd

Cú pháp:
```
$ xvhosts restart_apache
```

#### Đăng ký đường dẫn của ứng dụng
Tính năng này cho phép bạn đăng ký đường dẫn đến thư mục ứng dụng của XVHM vào biến môi trường đường dẫn của Windows. Thông thường, bạn hiếm khi cần điều này. Nó chỉ hữu ích khi bạn cần thay đổi tên thư mục ứng dụng của XVHM hoặc di chuyển nó đến một vị trí khác.

Để thực hiện việc này, sau khi bạn đã thay đổi vị trí hoặc tên của thư mục XVHM, hãy điều hướng đến vị trí mới của thư mục XVHM trong dấu nhắc lệnh và chạy lệnh sau:

Cú pháp:
```
$ xvhosts register_path
```

> Lưu ý: Bạn cần cho phép quy trình này được thực thi với quyền Administrator.

## Cấu hình
Tất cả cấu hình được đặt trong một tệp ini có tên `settings.ini` nằm trong thư mục ứng dụng của XVHM. Cấu trúc của tập tin này trông như sau:

```
[Section_1]
setting_1 = "value"
setting_2 = "value"

[Section_2]
setting_1 = "value"
setting_2 = "value"
...
```

Toàn bộ các cấu hình của XVHM bao gồm:
```
[DirectoryPaths]
;Đường dẫn đến thư mục Xampp của bạn.
Xampp = "D:\xampp"

[Suggestions]
;Đường dẫn thư mục dùng để đề xuất cho cấu hình DocumentRoot của tập tin vhost config trong mỗi quy trình tạo tên miền ảo.
;Biến {{host_name}} được sử dụng như nơi giữ chỗ cho tên miền ảo.
DocumentRoot = "D:\www\{{host_name}}"

;Một email dùng để đề xuất cho cấu hình ServerAdmin của tập tin vhost config file trong mỗi quy trình tạo tên miền ảo.
AdminEmail = "anhvudo@gmail.com"

[ListViewMode]
;Số lượng tên miền ảo sẽ được hiển thị trên mỗi trang khi liệt kê các tên miền ảo hiện có.
RecordPerPage = "2"

```

## Giấy phép
[MIT](LICENSE) © Jackie Do

## Lời cảm ơn
Hy vọng, ứng dụng này hữu ích cho các bạn.