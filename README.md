KMeansRGB.php
-------------
利用K-Means++算法对RGB像素点群进行聚类，从而实现提取图片主题色的目的。
### 使用方法
```
<?php
    $kRgb = new KMeansRGB('./test.jpg');
    $colors = $kRgb->process();
```
