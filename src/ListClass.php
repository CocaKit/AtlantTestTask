<?php

namespace App;

use Doctrine\DBAL\Connection;

class ListClass 
{
    private Connection $connection;
    private array $columns;
    private array $searchColumns;
    private string $searchName;
    private int $limit;
    private int $page;
    private string $urlController;
    private string $sql;
    private string $urlAction;
    private string $actionColName;
    private array $actionParameters;
    private string $actionName;
    private bool $originWhere;
    private bool $resultCount;

    public function __construct(string $urlController, Connection $connection, string $sql, 
                                int $page = 1, bool $originWhere = true, int $limit = 10)
    {
        $this->connection = $connection;
        $this->page = $page;
        $this->limit = $limit;
        $this->sql = $sql;
        $this->urlController = $urlController;
        $this->columns = [];
        $this->urlAction = '';
        $this->actionParameters = [];
        $this->actionName = '';
        $this->searchColumns = [];
        $this->originWhere = $originWhere;
        $this->searchName = 'Search';
        $this->actionColName = 'Action';
    }

    public function addColumn(string $columnName, string $prettyName, string $changeParameter = '',
                                                                      string $sqlColOutput = '')
    {
        $this->columns[$columnName] = ['prettyName' => $prettyName, 
                                       'changeParameter' => $changeParameter,
                                       'sqlColOutput' => $sqlColOutput];

    }

    public function setAction(string $url, string $actionName, array $parameters = [])
    {
        $this->urlAction = $url;
        $this->actionName = $actionName;
        $this->actionParameters = $parameters;
    }

    public function addSearch(string $columnName, string $parameter, bool $isFullText)
    {
        $this->searchColumns[$columnName] = [$parameter, $isFullText]; 
    }

    public function setSearchName($searchName)
    {
        $this->searchName = $searchName;
    }

    public function setActionColName($actionColName)
    {
        $this->actionColName = $actionColName;
    }

    public function getHTML()
    {
        $response = [];
        $htmlAddSearchParams = '';

        if (!empty($this->searchColumns)) {
            $checkFirst = true;
            $whereString = '';
            foreach ($this->searchColumns as $columnName =>  [$parameter, $isFullText]) {
                $parameter = pg_escape_string($parameter);
                if (!empty($parameter)) {
                    $lang = $this->getLanguage($parameter);

                    if ($this->originWhere && $checkFirst) {
                        $whereString .= 'WHERE ';
                    }

                    if ($checkFirst) {
                        $checkFirst = false;
                    } else {
                        $whereString .= "AND ";
                    }

                    if ($isFullText) {
                        $whereString .= "to_tsvector('$lang', $columnName) @@ plainto_tsquery('$lang', '$parameter') ";
                    } else {
                        $whereString .= "$columnName ILIKE '%$parameter%'";
                    }

                    $htmlAddSearchParams .= "<input type='hidden' id='" . $columnName 
                                          . "' name='" . $columnName . "' value='" 
                                          . $parameter . "'>";
                }
            }
            if (!empty($whereString)) {
                $this->sql = str_replace('CHANGE_WHERE', $whereString, $this->sql);
            }
        }

        $rows = $this->connection->fetchAllAssociative($this->sql); 

        $response['rowsCount'] = count((array)$rows);

        $pageCount = ceil(count((array)$rows) / $this->limit);

        $rows = array_slice((array)$rows, ($this->page - 1) * $this->limit, $this->limit);
        

        $htmlList = '<table><tr>';
        foreach ($this->columns as $columnName => $columnValues) {
            $htmlList .= '<th>' . $columnValues['prettyName'] . '</th>';
        }   
        if (!empty($this->urlAction)) {
            $htmlList .= '<th>' . $this->actionColName . '</th>';
        }
        $htmlList .= '</tr>';

        foreach ($rows as $row) {
            $htmlList .= '<tr>';
            foreach ($this->columns as $columnName => $columnValues) {
                $target = $row[$columnName];
                if (!empty($columnValues['changeParameter'])) {
                        if (!empty($columnValues['sqlColOutput'])) {
                            $lang = $this->getLanguage($this->searchColumns[$columnName][0]); 
                            $var = str_replace('CHANGE_LANG', $lang, $columnValues['changeParameter']);
                            $var = str_replace('CHANGE_TARGET', pg_escape_string($target), $var);
                            $var = str_replace('CHANGE_QUERY', pg_escape_string($this->searchColumns[$columnName][0]), $var);
                            $parameter = $this->connection->fetchAllAssociative($var)
                                                                                [0]
                                                                                [$columnValues['sqlColOutput']]; 
                        } else {
                            eval($columnValues['changeParameter']);
                        }
                } else {
                    $parameter = $target;
                }
                $htmlList .= '<td>' . $parameter . '</td>';
            }
            if (!empty($this->urlAction)) {
                $htmlList .= "<td><form action='" . $this->urlAction . "'>";
                foreach ($this->actionParameters as $parameter) {
                    $htmlList .= "<input type='hidden' id='" . $parameter . "' name='" 
                              . $parameter . "' value='" . $row[$parameter] . "'>";
                }
                $htmlList .= "<input type='submit' value='" . $this->actionName . "'></form></td>";
            }
            $htmlList .= '</tr>';
        }

        $htmlList .= '</table>';

        $response['htmlList'] = $htmlList;

        $startPage = $this->page - 2 > 0 ? $this->page - 2 : 1;
        $endPage = $this->page + 2 < $pageCount ? $this->page + 2 : $pageCount;

        $htmlPages = "";
        if ($startPage !== 1) {
            $htmlPages .= "<form action='" . $this->urlController . "'>"
                        . "<input type='hidden' id='page' name='page' value='" . 1 . "'>"
                        . $htmlAddSearchParams
                        . "<input type='submit' value='<<'></form>";
        }

        if ($this->page > 1) {
            $htmlPages .= "<form action='" . $this->urlController . "'>"
                        . "<input type='hidden' id='page' name='page' value='" . $this->page - 1 . "'>"
                        . $htmlAddSearchParams
                        . "<input type='submit' value='<'></form>";
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i === $this->page) {
                $htmlPages .= "<form>"
                            . "<input type='button' value='" . $i . "'></form>";
            }
            else {
                $htmlPages .= "<form action='" . $this->urlController . "'>"
                            . "<input type='hidden' id='page' name='page' value='" . $i . "'>"
                            . $htmlAddSearchParams
                            . "<input type='submit' value='" . $i . "'></form>";
            }
        }

        if ($this->page < $pageCount) {
            $htmlPages .= "<form action='" . $this->urlController . "'>"
                        . "<input type='hidden' id='page' name='page' value='" . $this->page + 1 . "'>"
                        . $htmlAddSearchParams
                        . "<input type='submit' value='>'></form>";
        }

        if ($endPage !== $pageCount) {
            $htmlPages .= "<form action='" . $this->urlController . "'>"
                        . "<input type='hidden' id='page' name='page' value='" . $pageCount . "'>"
                        . $htmlAddSearchParams
                        . "<input type='submit' value='>>'></form>";
        }

        $response['htmlPages'] = $htmlPages;

        if (!empty($this->searchColumns)){
            $htmlSearch = "<div class='list-form-search'><form action='" . $this->urlController . "'>";
            foreach ($this->searchColumns as $columnName => [$parameter, $isFullText]) {
                $htmlSearch .= "<input type='text' id='" . $columnName . "' name='" . $columnName 
                       . "' value='" . htmlentities($parameter) 
                       . "' placeholder='" . $this->columns[$columnName]['prettyName'] ."'>";
            }
            $htmlSearch .= "<input type='submit' value='" . $this->searchName . "'></form></div>";
            $response['htmlSearch'] = $htmlSearch;
        }

        return $response;
    }

    private function getLanguage(string $parameter) 
    {
        if (preg_match('/[А-Яа-яЁё]/u', $parameter)){
            return 'russian';
        }
        else {
            return 'english';
        }
    }
}