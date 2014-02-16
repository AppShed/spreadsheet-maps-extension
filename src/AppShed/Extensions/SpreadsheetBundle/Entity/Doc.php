<?php

namespace AppShed\Extensions\SpreadsheetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Organisation
 *
 * @ORM\Table()
 * @ORM\Entity()
 */
class Doc
{

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @var Date
     * @ORM\Column(name="date", type="datetime", nullable=true)
     */
    protected $date;

    /**
     * Get Date
     * @return Date
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set Date
     *
     * @param string $Date
     * @return Guest
     */
    public function setDate($x)
    {
        $this->date = $x;
        return $this;
    }

    /**
     * @var titles
     * @ORM\Column(name="titles", type="array",  nullable=true)
     */
    protected $titles;

    /**
     * Get titles
     * @return Titles
     */
    public function getTitles()
    {

        return is_array($this->titles) ? $this->titles : array();
    }

    /**
     * Set titles
     *
     * @param string $titles
     * @return Doc
     */
    public function setTitles($titles)
    {
        $this->titles = $titles;
        return $this;
    }

    /**
     * @var url
     * @ORM\Column(name="url", type="string", length=355, nullable=true)
     */
    protected $url;

    /**
     * Get url
     * @return Url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set url
     *
     * @param string $url
     * @return Doc
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @var key
     * @ORM\Column(name="dockey", type="string", length=255, nullable=true)
     */
    protected $key;

    /**
     * Get key
     * @return Key
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set key
     *
     * @param string $key
     * @return Doc
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @var itemsecret
     * @ORM\Column(name="itemsecret", type="string", length=255, nullable=true)
     */
    protected $itemsecret;

    /**
     * Get itemsecret
     * @return Itemsecret
     */
    public function getItemsecret()
    {
        return $this->itemsecret;
    }

    /**
     * Set itemsecret
     *
     * @param string $itemsecret
     * @return Doc
     */
    public function setItemsecret($itemsecret)
    {
        $this->itemsecret = $itemsecret;
        return $this;
    }

    /**
     * @var filters
     * @ORM\Column(name="filters", type="array")
     */
    protected $filters;

    /**
     * Get filters
     * @return Filters
     */
    public function getFilters()
    {
        return is_array($this->filters) ? $this->filters : array();
    }

    /**
     * Set filters
     *
     * @param string $filters
     * @return Doc
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
        return $this;
    }

}
