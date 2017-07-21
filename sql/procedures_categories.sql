DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_categories_save`(
pdescategory varchar(32)
)
BEGIN
	
    DECLARE vidcategory INT;
    
	INSERT INTO tb_categories (descategory)
    VALUES(pdescategory);
    
    SET vidcategory = LAST_INSERT_ID();
    
    SELECT * FROM tb_categories WHERE idcategory = LAST_INSERT_ID();
    
END ;;

delimiter ;;

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_categories_delete`(
pidcategory INT
)
BEGIN
    
    DELETE FROM tb_categories WHERE idcategory = pidcategory;
    
END ;;