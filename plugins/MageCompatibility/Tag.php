<?php
namespace MageCompatibility;

use Netresearch\Logger;

use \dibi as dibi;

class Tag
{
    protected $config;

    protected function getFieldsToSelect()
    {
        return array(
            'ts.signature_id',
            'name',
            's.path',
            'definition'
        );
    }

    /**
     * determine which Magento versions seem to be compatible to this call
     * 
     * @return array Array of Magento versions (e.g. [ 'CE 1.6.2.0', 'CE 1.7.0.2' ])
     */
    public function getMagentoVersions()
    {
        if ($fixedVersions = $this->getFixedVersions()) {
            return $fixedVersions;
        }
        $query = 'SELECT ' . implode(', ', $this->getFieldsToSelect()) . '
            FROM [' . $this->table . '] t
            INNER JOIN [' . $this->tagType . '_signature] ts ON (t.id = ts.' . $this->tagType . '_id)
            INNER JOIN [signatures] s ON (ts.signature_id = s.id)
            WHERE t.name = %s
            GROUP BY s.id'
            ;
        try {
            /* find all signatures matching that call */
            $result = dibi::query($query, $this->getName());
        } catch (\DibiDriverException $e) {
            dibi::test($query, $this->getName());
            throw $e;
        }
        $versions = array();
        if (is_null($result) || 0 == count($result)) {
            return null;
        }

        /* get best matching signature id */
        if (1 < count($result)) {
            $signatureIds = $this->getBestMatching($result->fetchAll());
        } else {
            try {
                $signatureIds = array_keys($result->fetchPairs());
            } catch (\DibiDriverException $e) {
                dibi::test($query, $signatureId);
                throw $e;
            }
        }
        if (false == is_array($signatureIds) || 0 == count($signatureIds)) {
            Logger::warning('Could not find any matching definition of ' . $this->name);
            return null;
        }

        /* fetch Magento versions */
        $query = 'SELECT CONCAT(edition, " ", version) AS magento
            FROM [magento_signature] ms
            INNER JOIN [magento] m ON (ms.magento_id = m.id)
            WHERE ms.signature_id IN (%s)
            GROUP BY magento'
            ;
        try {
            return dibi::fetchPairs($query, $signatureIds);
        } catch (\DibiDriverException $e) {
            dibi::test($query, $signatureId);
            throw $e;
        }
    }

    /**
     * Get fix Magento version list, if that tag is known as an exceptional case
     *
     * @return array|null
     */
    protected function getFixedVersions()
    {
        /* since there are some tags being supported in Magento versions we already know
         * but not recognized correctly
         * Thats the case, if that tag is neither defined in code nor a database field
         * with a corresponding name, but e.g. by setting a object property via magic setter
         */
        $fixedVersions = json_decode(file_get_contents(
            dirname(__FILE__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'fixedVersions.json'
        ));
        foreach ($fixedVersions->{$this->table} as $candidate) {
            if ($this->getName() == $candidate->name) {
                if (array_key_exists('class', $this->context)
                    && 0 < strlen($this->context['class'])
                    && $candidate->classes
                ) {
                    foreach ($candidate->classes as $class) {
                        $class = str_replace('*', '.*', $class);
                        if (preg_match("/$class/", $this->context['class'])) {
                            return $this->getMagentoVersionsLike($candidate->versions);
                        }
                    }
                } else {
                    return $this->getMagentoVersionsLike($candidate->versions);
                }
            }
        }
    }

    protected function getMagentoVersionsLike($pattern)
    {
        $pattern = str_replace("*", "%", $pattern);

        $query = 'SELECT CONCAT(edition, " ", version) AS magento
            FROM [magento] m
            WHERE CONCAT(edition, " ", version) LIKE ?'
            ;
        try {
            return dibi::fetchPairs($query, $pattern);
        } catch (\DibiDriverException $e) {
            dibi::test($query, $pattern);
            throw $e;
        }
    }

    /**
     * determine best matching signature
     * 
     * @param array $candidates Array of DibiRows
     * @return array
     */
    protected function getBestMatching($candidates)
    {
        $candidates = $this->filterByParamCount($candidates);
        $candidates = $this->filterByContext($candidates);
        $signatureIds = array();
        foreach ($candidates as $candidate) {
            $signatureIds[] = $candidate->signature_id;
        }
        return $signatureIds;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    protected function filterByParamCount($candidates)
    {
        return $candidates;
    }

    protected function filterByContext($candidates)
    {
        return $candidates;
    }
}
