<?php

/**
 * 利用K-Means算法提取图片主题色
 *
 * @author Barry
 */
class KMeansRGB
{
    /**
     * @var int 提取主题色个数
     */
    protected $k;

    /**
     * @var array 主题色聚类
     */
    protected $clusters = [];

    /**
     * @var array 图片像素点rgb值
     */
    protected $points = [];

    /**
     * @var int 图片等比缩放最大宽高
     */
    protected $maxImageSize = 100;

    /**
     * @var int|float 阈值，当聚类中心点浮动范围低于这个值时停止K-Means算法迭代
     */
    protected $threshold = 1;

    /**
     * 初始化
     *
     * @param string $image 图片路径或URL
     * @param int $k 提取主题色个数
     */
    public function __construct($image, $k = 5)
    {
        if ($k >= 1) {
            $this->k = (int)$k;
        }
        $this->generationRGBPoints($image);
    }

    /**
     * @param int $maxImageSize
     */
    public function setMaxImageSize($maxImageSize)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * @param int|float $threshold
     */
    public function setThreshold($threshold)
    {
        $this->threshold = $threshold;
    }

    /**
     * 主方法
     *
     * @return array
     */
    public function process()
    {
        $this->generateInitCentriods($this->k);
        while ($this->iterate()) {};
        return $this->clusters;
    }

    /**
     * K-Means算法迭代
     *
     * @return bool
     */
    protected function iterate()
    {
        $continue = false;

        foreach ($this->points as $point) {
            $closestCluster = $this->getClosestCluster($point);
            $minPoints[$closestCluster[0]][] = $point;
        }
        foreach ($this->clusters as $index => $cluster) {
            if (! array_key_exists($index, $minPoints)) {
                continue;
            }
            $centriod = $this->calculateCentriod($minPoints[$index]);
            $this->clusters[$index]['rgb'] = $centriod;
            $this->clusters[$index]['weight'] = count($minPoints[$index]);
            $offsetDistance = self::getRGBColorDistance($centriod, $cluster['rgb']);
            if ($offsetDistance >= $this->threshold) {
                $continue = true;
            }
        }

        return $continue;
    }

    /**
     * 初始化RGB像素点群
     *
     * @param $image 图片路径或URL
     */
    protected function generationRGBPoints($image)
    {
        $imagick = new Imagick($image);
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        if ($width > $this->maxImageSize || $height > $this->maxImageSize) {
            $ratio = $width / $height;
            if (1 > $ratio) {
                $thumbHeight = $this->maxImageSize;
                $thumbWidth = floor($thumbHeight * $ratio);
            } else {
                $thumbWidth = $this->maxImageSize;
                $thumbHeight = floor($thumbWidth / $ratio);
            }
            $imagick->thumbnailImage($thumbWidth, $thumbHeight);
        }
        $it = $imagick->getPixelIterator();
        $it->resetIterator();
        while ($row = $it->getNextIteratorRow()) {
            foreach ($row as $pixel) {
                $color = $pixel->getColor();
                $this->points[] = [$color['r'], $color['g'], $color['b']];
            }
        }
    }

    /**
     * 计算聚类中心点
     *
     * @param array $points 聚类中的所有点
     * @return array
     */
    protected function calculateCentriod($points) {
        $centriod = [0, 0, 0];
        $count = count($points);
        foreach ($points as $point) {    
            foreach ($point as $key => $value) {  
                $centriod[$key] += $value;
            }  
        }    
        foreach ($centriod as &$value) {  
            $value = $value / $count;  
        }  
        return $centriod;
    }

    /**
     * 初始化聚类中心点
     *
     * @param $k 聚类个数
     */
    protected function generateInitCentriods($k)
    {
        // 随机生成一个聚类中心点
        $count = count($this->points);
        $index = mt_rand(0, $count - 1);
        $this->clusters[0]['rgb'] = $this->points[$index];
        $this->clusters[0]['weight'] = 0;

        // 按照概率分布生成剩余聚类中心点，越远的点被选择为中心点的概率越高（即K-Means++）
        for ($i = 1; $i < $k; $i++) {
            $sum = 0;
            foreach ($this->points as $key => $point) {
                $closestCluster = $this->getClosestCluster($point);
                $sum += $distances[$key] = $closestCluster[1];
            }
            $sum = mt_rand(0, $sum);
            foreach ($this->points as $key => $point) {
                if (($sum -= $distances[$key]) > 0) {
                    continue;
                }
                $this->clusters[$i]['rgb'] = $point;
                $this->clusters[$i]['weight'] = 0;
                break;
            }
        }
    }

    /**
     * 计算并获取离的最近的聚类下标和距离
     *
     * @return array
     */
    protected function getClosestCluster($point)
    {
        foreach($this->clusters as $index => $cluster) {
            $distance = self::getRGBColorDistance($point, $cluster['rgb']);
            if (! isset($minDistance) || $distance < $minDistance) {
                $minDistance = $distance;
                $minIndex = $index;
            }
        }
        return [$minIndex, $minDistance];
    }

    /**
     * RGB加权欧式距离公式（weighted Euclidean distance in R'G'B'）
     * 
     * @return float
     */
    public static function getRGBColorDistance($p1, $p2)
    {
        $rmean = ($p1[0] + $p2[0]) / 2;
        $r = $p1[0] - $p2[0];
        $g = $p1[1] - $p2[1];
        $b = $p1[2] - $p2[2];
        return sqrt((((512 + $rmean) * $r * $r) >> 8) + 4 * $g * $g + (((767 - $rmean) * $b * $b) >> 8));
    }
}
