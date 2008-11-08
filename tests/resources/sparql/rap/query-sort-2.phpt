return array(
    'name'              => 'ask-02.rq',
    'group'             => 'RAP Ask Test Cases',
    'query'             => 'PREFIX foaf:       <http://xmlns.com/foaf/0.1/>
    SELECT ?name
    WHERE { ?x foaf:name ?name }
    ORDER BY DESC(?name)'
);