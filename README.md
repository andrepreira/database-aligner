# Database Aligner

Alinhador de estruturas de banco de dados MySQL baseado em um comparador criado por [William Costa](https://github.com/william-costa/database-comparer)
## Funcionamento
Recebe as chaves de acesso de dois bancos (Banco A e Banco B) e procura a diferença entre os dois, aplicando no Banco B configurações que o Banco A possui.
## Informação
O alinhador apenas cria tabelas se elas não existirem, utilizando o `CREATE TABLE IF NOT EXISTS` ***statment***.\
O alinhador utiliza o `DROP` apenas em ***constraints*** para evitar conflitos caso elas já existam.
## Aviso
Cuidado ao utilizar o Database Aligner, pois inverter a ordem dos bancos de dados pode gerar problemas. Não me responsabilizo pelo seu uso, a ferramenta ainda está **sob desenvolvimento**.