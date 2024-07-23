<?php
session_start();
// Paramètres de connexion à la base de données
// Paramètres de connexion à la base de données
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "f_evaluation";
$port = 3307; // Port MySQL

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Vérifiez la connexion
if ($conn->connect_error) {
    die("La connexion a échoué: " . $conn->connect_error);
}


// Récupération des options de filière
$sql_filier = "SELECT ID_filiere, Nom_filiere FROM Filiere";
$result_filier = $conn->query($sql_filier);
$filiereArray = array();
if ($result_filier->num_rows > 0) {
    while ($row_filier = $result_filier->fetch_assoc()) {
        $filiereArray[$row_filier["ID_filiere"]] = $row_filier["Nom_filiere"];
    }
}

// Initialisation des variables
$a_scolaire = array();
$students = array();
$selected_filiere = "";
$selected_annee = "";
$selected_stage = "";
$selected_numero_d_apogee = "";
$stage_options = array();
$membres_jury = array();
$numMembers = 0;
$title = "";

// Vérification si le formulaire est soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_filiere = $_POST['filiere'];
    $selected_annee = $_POST['a_scolaire'];
    $selected_stage = $_POST['stage_type'] ?? "";
    $selected_numero_d_apogee = isset($_POST['etudiant']) ? $_POST['etudiant'] : "";
    $numMembers = isset($_POST['numMembers']) ? intval($_POST['numMembers']) : "";
        
    // Récupérer les années scolaires basées sur la filière sélectionnée
    $sql_a_scolaire = "SELECT ID_annee, Description_annee 
                       FROM Annee_Scolaire 
                       WHERE ID_filiere=" . intval($selected_filiere);

    $result_a_scolaire = $conn->query($sql_a_scolaire);
    if ($result_a_scolaire->num_rows > 0) {
        while ($row_a_scolaire = $result_a_scolaire->fetch_assoc()) {
            $a_scolaire[$row_a_scolaire["ID_annee"]] = $row_a_scolaire["Description_annee"];
        }
    } 

    // Déterminer les options de stage basées sur l'année scolaire sélectionnée
    if (!empty($selected_annee)) {
        $sql_stage = "SELECT s.ID_stage, s.Type_stage 
                      FROM Stage s
                      JOIN stage_par_annee_scolaire spa ON s.ID_stage = spa.ID_stage
                      WHERE spa.ID_annee = " . intval($selected_annee);
        
        $result_stage = $conn->query($sql_stage);
        if ($result_stage->num_rows > 0) {
            while ($row_stage = $result_stage->fetch_assoc()) {
                $stage_options[$row_stage["ID_stage"]] = $row_stage["Type_stage"];
            }
        }
    }
    // Définir le titre en fonction du stage sélectionné
      if (!empty($selected_stage)) {
        $sql_stage_title = "SELECT Abbreviation_stage FROM Stage WHERE ID_stage = " . intval($selected_stage);
        $result_stage_title = $conn->query($sql_stage_title);
        if ($result_stage_title->num_rows > 0) {
            $row_stage_title = $result_stage_title->fetch_assoc();
            $title = $row_stage_title["Abbreviation_stage"];
        }
    }

    // Récupérer les étudiants basés sur la filière et l'année scolaire sélectionnées
    if (!empty($selected_filiere) && !empty($selected_annee)) {
        $sql_students = "SELECT Numero_D_apogee, CONCAT(Nom_etudiant, ' ', Prenom_etudiant) AS FullName 
                         FROM Etudiant 
                         WHERE ID_filiere=" . intval($selected_filiere) . " AND ID_annee=" . intval($selected_annee);
        $result_students = $conn->query($sql_students);
        if ($result_students->num_rows > 0) {
            while ($row_student = $result_students->fetch_assoc()) {
                $students[$row_student["Numero_D_apogee"]] = $row_student["FullName"];
            }
        } 
    }

    // Récupérer les membres du jury en fonction de la filière sélectionnée
    $sql_jury = "SELECT ID_prof,CONCAT(Nom_prof, ' ', Prenom_prof) AS FullName 
                 FROM Membre_Jury 
                 WHERE ID_filiere=" . intval($selected_filiere);
    $result_jury = $conn->query($sql_jury);
    if ($result_jury->num_rows > 0) {
        while ($row_jury = $result_jury->fetch_assoc()) {
            // Ajouter les membres du jury seulement s'ils ne sont pas vides
        if (!empty(trim($row_jury["FullName"]))) {
            $membres_jury[$row_jury["ID_prof"]] = $row_jury["FullName"];
        }
    }
    } 

    // Vérifier si le bouton a été cliqué avant de procéder à l'insertion
    if (isset($_POST['insertData']) && $_POST['insertData'] === '1') {
    // Insertion des données dans la table Stage_Realise après calcul des notes
    if (!empty($selected_filiere) && !empty($selected_annee) && !empty($selected_stage) && !empty($selected_numero_d_apogee)) {
        $titre = $_POST['titre'];
        $encadrant = $_POST['encadrant'];
        $note_rapport = floatval($_POST['rapport']);
        $note_presentation = floatval($_POST['presentation']);
        $note_encadrant = floatval($_POST['encadrant_note']);

        // Récupérer les pourcentages pour la filière sélectionnée
        $sql_pourcentage = "SELECT Pourcentage_rapport, Pourcentage_presentation_orale, Pourcentage_encadrant 
                            FROM Pourcentage 
                            WHERE ID_filiere=" . intval($selected_filiere);
        $result_pourcentage = $conn->query($sql_pourcentage);
        if ($result_pourcentage->num_rows > 0) {
            $row_pourcentage = $result_pourcentage->fetch_assoc();
            $pourcentage_rapport = $row_pourcentage["Pourcentage_rapport"];
            $pourcentage_presentation = $row_pourcentage["Pourcentage_presentation_orale"];
            $pourcentage_encadrant = $row_pourcentage["Pourcentage_encadrant"];

        // Calcul de la note finale
        $note_finale = ($note_rapport * $pourcentage_rapport) + 
                       ($note_presentation * $pourcentage_presentation) + 
                       ($note_encadrant * $pourcentage_encadrant);

        // Vérifier si l'entrée existe déjà
        $sql_check_for_Rapport = "SELECT Numero_D_apogee, ID_stage FROM Rapport 
                      WHERE Numero_D_apogee='$selected_numero_d_apogee' 
                      AND ID_stage='$selected_stage'";
        $result_check_for_Rapport = $conn->query($sql_check_for_Rapport);
        if ($result_check_for_Rapport->num_rows == 0) {
        // Préparer et exécuter l'insertion dans la table Rapport
        $sql_insert_for_Rapport = "INSERT INTO Rapport (Numero_D_apogee, ID_stage, Titre_Rapport , Nom_Prenom_encadrent, Note_rapport, Note_presentation_orale, Note_encadrant, Note_finale)
                       VALUES ('$selected_numero_d_apogee','$selected_stage' , '$titre', ' $encadrant', '$note_rapport', '$note_presentation', '$note_encadrant', '$note_finale')";

            if ($conn->query($sql_insert_for_Rapport) === FALSE) {
            echo "Error: " . $sql_insert_for_Rapport . "<br>" . $conn->error;
            } 
        }
    }
    }
    // Vérifier que les champs nécessaires sont remplis
    if (!empty($selected_stage) && !empty($selected_numero_d_apogee)) {
        $date_soutenance = $_POST['date'];
        $heure_soutenance = $_POST['heure'];
        $lieu_soutenance = $_POST['salle'];
        $numMembers = intval($_POST['numMembers']);
        // Vérifier si l'entrée existe déjà
        $sql_check_for_Soutenance = "SELECT Numero_D_apogee, ID_stage FROM Soutenance
                      WHERE Numero_D_apogee='$selected_numero_d_apogee' 
                      AND ID_stage='$selected_stage'";
        $result_check_for_Soutenance = $conn->query($sql_check_for_Soutenance);
        if($result_check_for_Soutenance->num_rows == 0){    
        // Préparer et exécuter l'insertion dans la table Soutenance
        $sql_insert_soutenance = "INSERT INTO Soutenance (Date_soutenance, Heure_soutenance, Lieu_soutenance, ID_stage, Numero_D_apogee) 
                                  VALUES ('$date_soutenance', '$heure_soutenance', '$lieu_soutenance', '$selected_stage', '$selected_numero_d_apogee')";
    
    if ($conn->query($sql_insert_soutenance) === FALSE) {
        echo "Error: " . $sql_insert_soutenance . "<br>" . $conn->error;
        }else{
            // Récupérer l'ID de la soutenance insérée
            $id_soutenance = $conn->insert_id;
            // Nombre de membres du jury
            $numMembers = $_POST['numMembers'];
            // Boucle pour insérer chaque membre du jury
            for ($i = 1; $i <= $numMembers; $i++) {
                $membre_key = "membre_$i";
                $role_key = "role_$i";
            
                if (isset($_POST[$membre_key]) && isset($_POST[$role_key])) {
                    $id_jury = $_POST[$membre_key];
                    $role = $_POST[$role_key];

                    // Insérer les données dans la table Jury_Soutenance
                    $sql_insert_jury = "INSERT INTO Jury_Soutenance (ID_jury, ID_soutenance, role_du_jury) 
                                        VALUES ('$id_jury', '$id_soutenance', '$role')";
            
            if ($conn->query($sql_insert_jury) === FALSE) {
                echo "Error: " . $sql_insert_jury . "<br>" . $conn->error;
                            }
                        }
                    }  
                }
            }
        }
        $_SESSION['selected_filiere'] = $_POST['filiere'];
        $_SESSION['selected_annee'] = $_POST['a_scolaire'];
        $_SESSION['selected_stage'] = $_POST['stage_type'];
        $_SESSION['selected_etudiant'] = $_POST['etudiant'];

        // Redirect to g_pdf.php
        header("Location: generate_pdf.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="stylesheet" href="style_2.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="forme.css">
    <!--  -->
    <link href="https://fonts.googleapis.com/css2?family=Lato&display=swap" rel="stylesheet">
    <!--  -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

    <title>projet_3</title>
</head>
<body>
    <!-- sematic HTML -->
    <header>
        <div class="navbar">
            <div class="logo">
                <span href="#"> EST Fkih Ben Salah </a>
            </div>
            <ul class="links_1 ">
                <!-- <li><a href="#Info_D'etudiant"> Info_D'etudiant</a></li> -->
                <li><a href="#Mode dévaluation"> INFO_D'ÉTUDIANT & MODE D'ÉVALUTION </a></li>
                <li><a href="#Membres du jury"> MEMBRES DU JURY </a></li>
            </ul>
            <div class="button">
                <a href="login.html" class="action-button"> Déconnecter </a>
            </div>
            <div class="burger-menu-button">
                <i class="fa-solid fa-bars"></i>
            </div>
        </div>

        <div class="burgeu-menu open">
            <ul class="links ">
                 <!-- <li><a href="#Info_D'etudiant"> Info_D'etudiant</a></li> -->
                 <li><a href="#Mode dévaluation"> INFO_D'ÉTUDIANT & MODE D'ÉVALUTION </a></li>
                 <li><a href="#Membres du jury"> MEMBRES DU JURY </a></li>
            </ul>
            <div class="divider"></div>
            <div class="button-burger-menu-">
                <a href="login.html" class="action-button"> Déconnecter </a>
            </div>
        </div>
    </header>

    <!-- section : main -->
    <section class="main">
        <div>
            <h2> Fiche D'évaluation du Projet <br> <span>de Fin d'Etudiant (PFE)</span></h2>
            <h3> DUT (Promotion 2023/2024) </h3>
            <a href="#Mode dévaluation" class="main-btn"> Suivant </a>
        </div>
    </section>
    <!-- fin dyal main -->

    <!-- debut dyal section 1 -->
     <div class="body" id="Mode dévaluation">
        <div class="container">
        <header> INFO_D'ÉTUDIANT & MODE D'ÉVALUTION :</header>

        <form id="dataForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form first">
                <!-- Info_D'etudiant -->
                <div class="dettails personal">
                    <div class="div-span"><span class="title">Info_D'etudiant :</span></div>
                    

                <div class="fields">
                    <!-- 1 -->
                    <div class="input-field">
                        <label for="filiere">Filière :</label>
                        <select id="filiere" name="filiere" required onchange="this.form.submit()">
                            <option value="">Sélectionner une filière</option>
                            <?php
                                foreach($filiereArray as $id => $filier) {
                                    $selected = ($id == $selected_filiere) ? "selected" : "";
                                    echo "<option value=\"$id\" $selected>$filier</option>";
                                }
                            ?>
                        </select><br><br>
                    </div>

                    <div class="input-field">
                    <label for="a_scolaire">Année scolaire:</label>
                        <select id="a_scolaire" name="a_scolaire" required onchange="this.form.submit()">
                        <option value="">Sélectionner une année scolaire</option>
                        <?php
                            if (!empty($selected_filiere)) {
                                foreach($a_scolaire as $id => $annee) {
                                    $selected = ($id == $selected_annee) ? "selected" : "";
                                    echo "<option value=\"$id\" $selected>$annee</option>";
                                    }
                                }
                        ?>
                        </select><br><br>
                    </div>
                    <!-- 3 -->
                    <div class="input-field">
                        <label for="stage_type">Type de stage:</label>
                        <select id="stage_type" name="stage_type" required onchange="this.form.submit()">
                            <option value="">Sélectionner un type de stage</option>
                            <?php
                                if (!empty($selected_annee)) {
                                    foreach ($stage_options as $id => $stage) {
                                        $selected = ($id == $selected_stage) ? "selected" : "";
                                        echo "<option value=\"$id\" $selected>$stage</option>";
                                    }
                                }
                            ?>
                        </select><br><br>
                    </div>
                    <!-- 4 -->
                    <div class="input-field">
                            <label for="etudiant">Étudiant:</label>
                            <select id="etudiant" name="etudiant" required onchange="this.form.submit()">
                                <option value="">Sélectionner un etudiant</option>
                                <?php
                                    if (!empty($students)){
                                            foreach ($students as $id => $etudiant) {
                                                $selected = ($id == $selected_numero_d_apogee) ? "selected" : "";
                                                echo "<option value=\"$id\" $selected>$etudiant</option>";
                                            }
                                        }   
                                ?>
                            </select><br><br>
                    </div>
                    <!-- 5 -->
                    <div class="input-field">
                        <label for="titre">Titre du <?php echo htmlspecialchars($title); ?>:</label>
                        <input type="text" id="titre" name="titre" value="<?php echo isset($_POST['titre']) ? $_POST['titre'] : ''; ?>" required><br><br>
                    </div>
                    <!-- 6 -->
                    <div class="input-field">
                        <label for="encadrant">Nom et prénom de l’encadrant:</label>
                    <input type="text" id="encadrant" name="encadrant" value="<?php echo isset($_POST['rapport']) ? $_POST['rapport'] : ''; ?>" required><br><br>
                    </div>
                    <!-- 7 -->
                    <div class="input-field">
                        <label for="salle">lieu de soutenance:</label>
                        <input type="text" id="salle" name="salle" required><br><br>
                    </div>
                    <!-- <div class="date_heure_mobre"> -->
                        <!-- 8 -->
                        <div class="mohcine ezzahi_1">
                            <label for="date">Date de soutenance:</label>
                            <input type="date" id="date" name="date" required><br><br>
                        </div>
                        <!-- 9 -->
                        <div class="mohcine ezzahi_1">
                            <label for="heure">Heure de soutenance:</label>
                            <input type="time" id="heure" name="heure" required><br><br>
                        </div>
                        <!-- 10 -->
                        <div class="mohcine ezzahi_2">
                            <label for="numMembers">Nombre de membres de jury:</label>
                            <input type="number" id="numMembers" name="numMembers" value="<?php echo $numMembers; ?>"  min="1" max ="6" onchange="this.form.submit()" required><br><br>
                        </div>
                    <!-- </div> -->
                </div>
            </div>
            <!-- fin Info_Détudiant -->

             <!-- dubut Mode d'évaluation -->
            <div class="dettails id">
                    <span class="title">Mode d'évaluation :</span>

                    <div class="fields">
                        <!-- 1 -->
                        <div class="input-field">
                            <label for="rapport">Note du Rapport:</label>
                            <input type="number" id="rapport" name="rapport" min="0" max="20" step="0.1" value="<?php echo isset($_POST['rapport']) ? $_POST['rapport'] : ''; ?>" required><br><br>
                        </div>
                        <!-- 2 -->
                        <div class="input-field">
                            <label for="presentation">Note de la Présentation:</label>
                            <input type="number" id="presentation" name="presentation" min="0" max="20" step="0.1"  value="<?php echo isset($_POST['presentation']) ? $_POST['presentation'] : ''; ?>" required><br><br>
                        </div>
                        <!-- 3 -->
                        <div class="input-field">
                            <label for="encadrant_note">Note de l'Encadrant:</label>
                            <input type="number" id="encadrant_note" name="encadrant_note" min="0" max="20" step="0.1" value="<?php echo isset($_POST['encadrant_note']) ? $_POST['encadrant_note'] : ''; ?>" required><br><br>
                        </div>
                    </div>
            </div>
            <!-- fin Mode d'évaluation-->

            <!-- button suivant-->
            <!-- <button class="next-btn" type="submit">-->
                <a href="#Membres du jury" class="btntext"> Suivant</a>
                <i class="fa-solid fa-arrow-right"></i>
            <!--</button>-->
            <!-- fin button suivant-->
            
            </div>

        </form>
    </div>
     </div>
    <!-- fin dyal section 1-->

    <!-- debut dyal section 3 -->
    <div class="body_2" id="Membres du jury">
        <div class="container_2">
            <div class="wrap">
                <h2>Membres du jury :</h2>
                <!-- <a href="#Membres du jury" class="add">&plus; </a> -->
            </div>
            <?php for ($i = 1; $i <= $numMembers; $i++): ?>
                    <label for="membre_<?php echo $i; ?>">Membre du jury <?php echo $i; ?>:</label>
                    <select id="membre_<?php echo $i; ?>" name="membre_<?php echo $i; ?>" required>
                        <option value="">Sélectionner un membre du jury</option>
                        <?php foreach ($membres_jury as $id_prof => $membre): ?>
                            <option value="<?php echo $id_prof; ?>"><?php echo $membre; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="role_<?php echo $i; ?>">Rôle du membre <?php echo $i; ?>:</label>
                    <input type="text" id="role_<?php echo $i; ?>" name="role_<?php echo $i; ?>" required>
                    <br><br>
                <?php endfor; ?>
            
            <!-- Champ caché pour vérifier si le bouton a été cliqué -->
            <input type="hidden" name="insertData" id="insertData" value="0">
            <div class="bottons">        
                <!-- Bouton de soumission -->
                <button type="button" class="next-btn" onclick="submitForm()">
                    <a href="generate_pdf.php" class="btntext"> Soumettre</a>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
                <button class="back-btn" >
                    <a href="#Mode dévaluation" class="btntext"> Retour</a>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
    <!--  fin dyal section 3-->
    <!--  button dyal scroll -->
      <button id="btn"> ^ </button>
    <!--  fin de button de scroll-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script >

        
const burgerMenuButton = document.querySelector('.burger-menu-button');
const burgerMenuButtonIcon = document.querySelector('.burger-menu-button i');
const burgerMenu = document.querySelector('.burgeu-menu ');



burgerMenuButton.onclick =function ()
{
    burgerMenu.classList.toggle('open');
    const isOpen = burgerMenu.classList.contains('open');
    burgerMenuButtonIcon.classList= isOpen? 'fa-solid fa-xmark':'fa-solid fa-bars'
}


//js dyal button de scroll
let btn = document.getElementById('btn');

window.onscroll = function () {
if (window.scrollY >= 299) {
    btn.style.display = 'block';
} else {
    btn.style.display = 'none';
}
}

btn.onclick = function () {
window.scroll({
    left: 0,
    top: 0,
    behavior: 'smooth',
});
}

$(document).ready(function() {
$('#filiere').select2({
    placeholder: "Sélectionner une filiere",
    allowClear: true
});

$('#etudiant').select2({
    placeholder: "Sélectionner un etudiant",
    allowClear: true
});  
});

function submitForm() {
// Modifier la valeur du champ caché pour indiquer que le bouton a été cliqué
document.getElementById('insertData').value = '1';
// Soumettre le formulaire
document.forms[0].submit();
}

function filterMembers() {
let selectedValues = Array.from(document.querySelectorAll('select[name^="membre_"]')).map(select => select.value);
document.querySelectorAll('select[name^="membre_"] option').forEach(option => {
if (option.value) {
    option.disabled = selectedValues.includes(option.value) && !option.selected;
    }
});
}

document.querySelectorAll('select[name^="membre_"]').forEach(select => {
select.addEventListener('change', filterMembers);
});

window.addEventListener('DOMContentLoaded', filterMembers); // Filtre initial au chargement de la page
/** */
document.getElementById('Mode dévaluation').addEventListener('change', function(e) {
e.preventDefault();

var formData = new FormData(this);

fetch('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', {
  method: 'POST',
  body: formData
})
.then(response => response.text())
.then(data => {
  // Mettre à jour uniquement la partie nécessaire de la page
  // Par exemple, si vous avez une div avec l'ID 'result' :
  /document.getElementByclass('result').innerHTML = data;
})
.catch(error => console.error('Erreur:', error));
});
// Avant la soumission du formulaire
window.addEventListener('beforeunload', function() {
sessionStorage.setItem('scrollPosition', window.pageYOffset);
});

// Après le chargement de la page
window.addEventListener('load', function() {
if (sessionStorage.getItem('scrollPosition') !== null) {
  window.scrollTo(0, sessionStorage.getItem('scrollPosition'));
  sessionStorage.removeItem('scrollPosition');
}
});</script>
</body>
</html>
<?php 
    // Fermer la connexion
    $conn->close();
    ?>
