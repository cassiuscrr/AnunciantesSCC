<?php
/*
Plugin Name: Anunciantes SCC	
Plugin URI: https://pensae.site/
Description: Plugin dos Anunciantes do SCC. Deve ser sempre atualizado. Feature: Integração com AdRotate
Author: Pensae!
Author URI: https://pensae.site/
Version: 1.0.5
*/
add_shortcode('scc-anunciante', 'ShortcodeAnunciante');
function ShortcodeAnunciante($param){

    $colecao 		= "img_".$param['type']; 	/* NOME DO CAMPO */
    $img_colecao 	= $param['type']; 			/* IMG NOME DO CAMPO */
	
	/* BUSCA TODOS OS ANUNCIANTES RANDOMICAMENTE - E RETORNA UM ITEM  */
	$allAnunciante = new WP_Query([
		"post_type" 				=> "anunciante",
		"nopaging"					=> false,
		"paged"						=> 1,
		"posts_per_page"			=> 1,
		"orderby"					=> "rand",
	]);
	if( !$allAnunciante->have_posts() ):
		return "";
	endif;
	/* SORTEIA UM RESULTADO DENTRO DE ANUNCIANTES E SALVA O OBJ */	
	$anunciante = $allAnunciante->posts[0];

	/* BUSCA OS CAMPOS DE COLECAO */
	$banners = get_field($colecao, $anunciante);
	if( $banners ):
		foreach( $banners as $x => $banner ):
			$adrotate[$x] = $banner[ $img_colecao ];
		endforeach;
	else:
		return "";
	endif;
	
	/* MISTURAAA */
	shuffle($adrotate);	
	
	/* MONTA ARQUIVO FINAL */	
    $final = [
		"anunciante"	=> $anunciante,
		"adrotate"		=> $adrotate[0],
        "type"			=> $img_colecao
    ];
		
	/* COMECA A MONTAGEM DO ANUNCIANTE PLUS */	
	/* REGRA: PRIMEIRO VALIDA SE TEM ANINCIANTE NA DATA */
	$hoje = date('Ymd');
	
	/* VALIDAR SE É UMA CATEGORIA */
	if( is_category() ){
		/* BUSCA A CATEGORIA DA PAGINA */		
		$is_category = get_queried_object();

		$search = str_replace(";","", serialize((string)$is_category->term_id) );
		
		$allAnunciantePlus = new WP_Query([
			"post_type" 				=> "anunciante_plus",
			"paged"						=> 1,
			"posts_per_page"			=> 1,
			"meta_query" 				=> [
				[
					'relation' => 'AND',
					[
						"key"     => "time_start",
						"compare" => "<=",
						"value"   => $hoje
					],[
						"key"     => "time_end",
						"compare" => ">=",
						"value"   => $hoje
					],
					[
						"key"     => "onde_categoria",
						"compare" => "like",
						"value"   =>  $search 
					]
				]
			]
		]);
	}
	
	/* VALIDAR É UMA PAGINA OU POST */
	if( is_page() || is_single() ){
		/* BUSCA O ID DA PAGINA OU POST */
		$search = str_replace(";","", serialize((string)get_the_ID()) );
		$allAnunciantePlus = new WP_Query([
			"post_type" 				=> "anunciante_plus",
			"paged"						=> 1,
			"posts_per_page"			=> 1,
			"meta_query" 				=> [
				[
					'relation' => 'AND',
					[
						"key"     => "time_start",
						"compare" => "<=",
						"value"   => $hoje
					],[
						"key"     => "time_end",
						"compare" => ">=",
						"value"   => $hoje
					],
					[
						"key"     => "onde_id",
						"compare" => "like",
						"value"   =>  $search
					]
				]
			]
		]);
	}		
	if(isset($allAnunciantePlus) && $allAnunciantePlus->have_posts() ): 
		/* TEM ANUNCIANTE PLUS - TEM CONCORRENCIA */
		while( $allAnunciantePlus->have_posts() ): $allAnunciantePlus->the_post();
				/* BUSCA OS ID DO ANUNCIANTE CADASTRADO NA ROW */		
                while( have_rows("colecao") ): the_row();
                    $is_anunciante = get_sub_field( "anunciante" );
                    $colecao_anuncios[] = [
                        "the_ID" => $is_anunciante[0]->ID,
                        "visibilidade" => (int) get_sub_field( "visibilidade" )
                    ];                 
                endwhile;	
		endwhile;
		/* CRIA UM ARRAY DE ANUNCIANTES PARA O SORTEIO FINAL */
		foreach( $colecao_anuncios as $colecao_anuncio ){
            $anuncio[ $colecao_anuncio['the_ID'] ] = getFinal( $colecao_anuncio['the_ID'] , $img_colecao , $colecao ) + [ "visibilidade" =>  $colecao_anuncio['visibilidade'] ];
        }
		sorteio($anuncio, $final);
	else:
		showAnunciante( $final );
	endif;wp_reset_postdata();
}


function getFinal($id, $img_colecao, $colecao){
    $query_final = new WP_Query([
        "post_type" => "anunciante",
        "p" => $id
    ]);
    if($query_final->have_posts()){
        while( $query_final->have_posts() ): $query_final->the_post();
            if( have_rows($colecao) ):
                while( have_rows($colecao) ): the_row();
                    $adrotate[] = get_sub_field( $img_colecao );
                endwhile;
            else:
                return "";
            endif;
            shuffle($adrotate);
			$anunciante = $query_final->posts[0];
			$final = [
				"anunciante"	=> $anunciante,
				"adrotate"		=> $adrotate[0],
				"type"			=> $img_colecao
			];
        endwhile;
    }
    wp_reset_postdata();
    return $final;
}

function sorteio($plus, $concorrencia){
    foreach( $plus as $anunciante ){
        $visibilidade = $anunciante['visibilidade'];
        for($i = 1; $i <= $visibilidade; $i++){
            $sorteio[] = $anunciante['anunciante']->ID;
        }
    }
    if(count($sorteio) < 100){
        $visibilidade = 100 - count($sorteio);
        for($i = 1; $i <= $visibilidade; $i++){
            $sorteio[] = $concorrencia['anunciante']->ID;
        }
        $plus[ $concorrencia['anunciante']->ID ] = $concorrencia;
    }
    shuffle($sorteio);
    showAnunciante($plus[ $sorteio[0] ]);
}

function showAnunciante($arg){
    $show = $arg['type'];
    switch( $show ){
        case 'super_banner': templateSuperBanner($arg);
        break;
        case 'retangulo_medio': templateRetaguloMedio($arg);
        break;
        case 'retangulo_expansivo': templateRetagulExpansivo($arg);
        break;
        case 'paralax': templateParalax($arg);
        break;
    }
}

function templateSuperBanner($arg){
    $id = "anuncianteSCC-" . $arg['type'] . "-" . $arg['anunciante']->post_name;
    ?>
    <style>
        section#<?php echo $id; ?>{
            background: #f7f7f7;
            padding:15px;
        }
        section#<?php echo $id; ?> .sccTitulo{
            font-size: 8pt;
            letter-spacing: 1pt;
            padding:10px;
            text-align: center;       
        }
        section#<?php echo $id; ?> .sccLink{
            text-align: center;
        }
        section#<?php echo $id; ?> .sccLink a{
            text-decoration: none;
        }
        section#<?php echo $id; ?> .sccLink a img{
            width: 100%;
            max-width: 912px;
            height: auto;
        }
    </style>
    <section id="<?php echo $id;?>" class="anuncianteSCC-SuperBanner">
        <div class="sccTitulo"> publicidade </div>
        <div class="sccLink"> 
            <?php echo do_shortcode( $arg['adrotate'] );?>
        </div>
    </section>
    <?php
}

function templateRetaguloMedio($arg){
    $id = "anuncianteSCC-" . $arg['type'] . "-" . $arg['anunciante']->post_name;
    ?>
    <style>
        section#<?php echo $id; ?>{
            padding:5px;
			background: #f7f7f7;
			margin-bottom:50px;
        }
        section#<?php echo $id; ?> .sccTitulo{
            font-size: 8pt;
            letter-spacing: 1pt;
            padding:10px;
            text-align: center;       344
        }
        section#<?php echo $id; ?> .sccLink{
            text-align: center;
        }
        section#<?php echo $id; ?> .sccLink a{
            text-decoration: none;
        }
        section#<?php echo $id; ?> .sccLink a img{
            width: 100%;
            //max-width: 344px;
            height: auto;
        }
    </style>
    <section id="<?php echo $id;?>" class="anuncianteSCC-RetanguloMedio">
        <div class="sccTitulo"> publicidade </div>
        <div class="sccLink"> 
            <?php echo do_shortcode( $arg['adrotate'] );?>
        </div>
    </section>
    <?php
}

function templateRetagulExpansivo($arg){
    $id = "anuncianteSCC-" . $arg['type'] . "-" . $arg['anunciante']->post_name;
    ?>
    <style>
        section#<?php echo $id; ?>{
			background: #f7f7f7;
            padding:5px;
        }
        section#<?php echo $id; ?> .sccTitulo{
            font-size: 8pt;
            letter-spacing: 1pt;
            padding:10px;
            text-align: center;       
        }
        section#<?php echo $id; ?> .sccLink{
            text-align: center;
        }
        section#<?php echo $id; ?> .sccLink a{
            text-decoration: none;
        }
        section#<?php echo $id; ?> .sccLink a img{
            width: 100%;
            //max-width: 344px;
            height: auto;
        }
    </style>
    <section id="<?php echo $id;?>" class="anuncianteSCC-RetanguloExpansivo">
        <div class="sccTitulo"> publicidade </div>
        <div class="sccLink"> 
            <?php echo do_shortcode( $arg['adrotate'] );?>
        </div>
    </section>
    <?php
}

function templateParalax($arg){
    $id = "anuncianteSCC-" . $arg['type'] . "-" . $arg['anunciante']->post_name;
    ?>
    <style>
        section#<?php echo $id; ?>{
            background: #f7f7f7;
            padding:5px;
        }
        section#<?php echo $id; ?> .sccTitulo{
            font-size: 8pt;
            letter-spacing: 1pt;
            padding:10px;
            text-align: center;  
        }
        section#<?php echo $id; ?> .sccLink a{
            display: block;
            width: 100%;
            height: 80vh;
            background-color: #f7f7f7;
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
        }
        @media screen and (max-width: 600px) {
            section#<?php echo $id; ?> .sccLink a{
                height: 60vh;
                background-size: contain;
            }
        }
    </style>
    <section id="<?php echo $id;?>" class="anuncianteSCC-Paralax">
        <div class="sccTitulo"> continua depois da publicidade </div>
        <div class="sccLink"> 
            <?php echo do_shortcode( $arg['adrotate'] );?>
        </div>
    </section>
    <?php
}