<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCursosTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('cursos', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('token', 50);
			$table->string('categoria', 200);
			$table->string('nome', 300);
			$table->integer('tipo');
			$table->string('codigo', 12);
			$table->string('titulo', 300)->nullable();
			$table->text('descricao');
			$table->text('modulos')->nullable();
			$table->text('conteudo');
			$table->float('inscricao', 12)->nullable();
			$table->string('Pagamento', 20)->nullable();
			$table->text('obs');
			$table->float('valor', 12)->nullable();
			$table->string('parcelas', 11);
			$table->string('meta_descricao', 200);
			$table->float('valor_parcela', 12);
			$table->text('Aquemsedestina')->nullable();
			$table->integer('autor');
			$table->string('url', 200);
			$table->integer('duracao');
			$table->string('unidade_duracao', 11);
			$table->enum('ativo', array('s','n'));
			$table->enum('publicar', array('n','s'));
			$table->enum('destaque', array('n','s'));
			$table->integer('ordenar');
			$table->dateTime('data');
			$table->dateTime('atualiza');
			$table->dateTime('atualizacao');
			$table->text('config');
			$table->text('aeronaves');
			$table->integer('professor');
			$table->enum('excluido', array('n','s'));
			$table->text('reg_excluido');
			$table->enum('deletado', array('n','s'));
			$table->text('reg_deletado');
			$table->string('tabela_site', 50);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('cursos');
	}

}
